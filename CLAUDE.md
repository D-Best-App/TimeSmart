# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

D-BEST TimeSmart is a PHP-based employee timekeeping system with three main interfaces:
- **Admin Portal** (`/admin/`) - User management, timesheet approval, reporting
- **Employee Portal** (`/user/`) - Clock in/out, timesheet viewing, edit requests
- **Kiosk Mode** (`/kiosk/`) - Badge/NFC scanning for PIN-less clocking

**Tech Stack:** PHP 8.3, MySQL/MariaDB, Nginx, Docker, TOTP 2FA

## Common Commands

### Development Environment

This application runs in Docker. The container is typically not exposed on host ports (bridge mode).

```bash
# Find container IP address
docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' <container_name>

# Access container shell
docker exec -it <container_name> /bin/bash

# View logs
docker logs <container_name>

# Restart services inside container
docker exec <container_name> supervisorctl restart all
```

### Database Operations

```bash
# Connect to database (from host)
mysql -h <db_host> -u <db_user> -p<db_pass> <db_name>

# Import schema
mysql -h <db_host> -u <db_user> -p<db_pass> <db_name> < Install/timeclock-schema.sql

# Backup database
mysqldump -h <db_host> -u <db_user> -p<db_pass> <db_name> > backup.sql
```

### Composer Dependencies

```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Required packages:
# - phpoffice/phpspreadsheet (Excel export)
# - spomky-labs/otphp (2FA TOTP)
# - endroid/qr-code (QR code generation)
# - vlucas/phpdotenv (environment variables)
```

### Deployment

```bash
# Run installation script (creates Docker container + database)
bash <(curl -s https://raw.githubusercontent.com/D-Best-App/Timesmart/main/Install/install.sh)
```

## Architecture

### Database Schema

**8 Tables (utf8mb4 charset):**

1. **users** - Employee records with bcrypt passwords, TagID (badge), ClockStatus, 2FA settings, GPS coordinates, profile photos
2. **admins** - Administrative accounts with bcrypt passwords, 2FA with JSON recovery codes
3. **timepunches** - Core time records with IN/OUT/Lunch times, GPS coordinates, IP addresses, calculated TotalHours
4. **pending_edits** - Employee timesheet edit requests (Pending/Approved/Rejected workflow)
5. **punch_changelog** - Audit trail for all punch modifications with EmployeeID, ChangedBy, OldValue/NewValue
6. **login_logs** - Security audit log with EmployeeID, IP, Timestamp
7. **settings** - Key-value configuration (e.g., EnforceGPS flag)

### Directory Structure

```
/admin/           - Admin portal (22 files): manage_users.php, reports.php, edits_timesheet.php, etc.
/user/            - Employee portal (10 files): dashboard.php, timesheet.php, settings.php, etc.
/kiosk/           - Badge scanning interface with audio feedback
/functions/       - Reusable utilities:
                    - clock_action.php (497 LOC) - Core punch/clock logic
                    - clock_handler.php - PIN verification (⚠️ SQL injection vulnerability)
                    - get_setting.php - Settings retrieval
                    - verify_pin.php - PIN validation
/auth/            - db.php - Database connection via mysqli with timezone setting
/Install/         - Deployment files: Dockerfile, docker-compose.yml, timeclock-schema.sql
/css/             - 26 stylesheet files
/js/              - 13 JavaScript files including kiosk logic
/docs/            - privacy.php, terms.php, report.php
```

### Configuration Management

**Environment Variables (`.env` file):**
- `DB_HOST` - Database server hostname
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_TIMEZONE` - Default: 'America/Chicago'

Loaded once in `/auth/db.php` via `vlucas/phpdotenv`, accessed via `$_ENV` superglobal.

**Runtime Settings Table:**
- `EnforceGPS` - Require GPS on clock actions (0/1)
- Queried via `/functions/get_setting.php`

### Authentication Flow

**Admin Login:**
```
/admin/login.php → CSRF + cooldown check → bcrypt verify → 2FA check → /admin/verify_2fa.php → dashboard
```

**Employee Login:**
```
/user/login.php → Parse name (First Last or Last, First) → Lookup with attempt tracking → 2FA check → /user/verify_2fa.php → dashboard
```

**Session Variables:**
- `$_SESSION['admin']` - Admin username (post-2FA)
- `$_SESSION['EmployeeID']` - Employee ID (post-2FA)
- `$_SESSION['2fa_admin_username']` - Temporary during 2FA flow
- `$_SESSION['temp_user_id']` - Temporary during 2FA flow
- `$_SESSION['csrf']` - CSRF token (64-char hex)

### Security Patterns

**Session Hardening:**
```php
session_set_cookie_params([
    'lifetime' => 0,
    'secure' => true,        // HTTPS only
    'httponly' => true,      // Prevent JS access
    'samesite' => 'Strict',  // CSRF protection
]);
```

**CSRF Protection:**
- Token: `bin2hex(random_bytes(32))` stored in `$_SESSION['csrf']`
- Validation: `hash_equals($_SESSION['csrf'], $_POST['csrf'])`
- Embedded in all POST forms

**Password Hashing:**
- bcrypt with `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 14])`
- Verification: `password_verify($input, $hash)`

**Prepared Statements:**
- Standard pattern used in 95% of queries:
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE ID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
```

**Lockout Mechanisms:**
- Admin login: 5 failures → 15-minute cooldown
- 2FA attempts: 5 failures → 15-minute cooldown
- 350ms artificial delay on failures (timing attack mitigation)

### Two-Factor Authentication (TOTP)

**Implementation via `spomky-labs/otphp`:**

**Setup Flow:**
1. Generate secret: `TOTP::create()`
2. Generate QR code: `endroid/qr-code` library
3. User scans with authenticator app (Google Authenticator, Authy, etc.)
4. Verify 6-digit code
5. Store secret in `TwoFASecret` field, set `TwoFAEnabled = 1`
6. Generate backup recovery codes (JSON array)

**Verification:**
- Code normalization: `preg_replace('/\D+/', '', $input)`
- Constant-time comparison: `hash_equals()` for recovery codes
- Verification: `$totp->verify($code)`

**Recovery Codes:**
- Stored as JSON in `TwoFARecoveryCode` field
- One-time use (deleted after use)
- Constant-time lookup prevents timing attacks

### Database Connection Pattern

**Single connection point: `/auth/db.php`**
```php
require_once __DIR__ . '/../vendor/autoload.php';
$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '{$_ENV['DB_TIMEZONE']}'");
```

Included in virtually every PHP file: `require '../auth/db.php';`

### Time Calculation

**Timezone:**
```php
date_default_timezone_set('America/Chicago');
```

**Hours Calculation:**
```php
$totalSeconds = strtotime($clockOut) - strtotime($clockIn);
if (!empty($lunchOut) && !empty($lunchIn)) {
    $totalSeconds -= (strtotime($lunchIn) - strtotime($lunchOut));
}
$totalHours = round($totalSeconds / 3600, 2);
```

### Key Workflows

**Clock In/Out:**
1. Badge scan or button click
2. `/functions/clock_action.php` or `/functions/clock_handler.php`
3. Validate employee + PIN
4. INSERT or UPDATE `timepunches` record
5. Update `users.ClockStatus` (In/Out/Lunch)
6. Calculate `TotalHours` on clock out

**Timesheet Edit Request:**
1. Employee views punch in `/user/dashboard.php`
2. Submit edit via `/user/submit_timesheet_edits.php`
3. INSERT into `pending_edits` with `Status='Pending'`
4. Admin reviews in `/admin/edits_timesheet.php`
5. On approval: UPDATE `timepunches`, INSERT `punch_changelog`
6. Delete from `pending_edits`

**Report Generation:**
1. Admin specifies date range + employee filter
2. Query `timepunches` with JOIN to `users`
3. Apply rounding rules if specified
4. Export as HTML, Excel (`phpoffice/phpspreadsheet`), or PDF (TCPDF)

## Critical Security Issues

### 🔴 SQL Injection Vulnerability

**File:** `/functions/clock_handler.php`
```php
// VULNERABLE CODE - Uses string interpolation instead of prepared statements
$user = $conn->query("SELECT * FROM users WHERE ID = '$empID' AND Pass = '$pass'");
```

**Fix:** Replace with prepared statement:
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE ID = ? AND Pass = ?");
$stmt->bind_param("ss", $empID, $pass);
$stmt->execute();
$result = $stmt->get_result();
```

### 🟡 File Upload Security

**File:** `/user/settings.php`
Profile photos uploaded to webroot (`../uploads/`). Validates MIME type and size but potential for PHP execution if extension validation bypassed.

### 🟡 Display Errors in Production

Multiple files have `ini_set('display_errors', 1);` which leaks system information.

## Default Credentials

**Admin Login:** `/admin/login.php`
- Username: `admin`
- Password: `password` (bcrypt hash in schema)

**Employee Login:** `/user/login.php`
- Credentials created by admin via `/admin/add_user.php`

## Access Patterns

**Public Pages:**
- `/index.php` - Status dashboard (shows all employee clock status)
- `/kiosk/index.php` - Badge scanning interface

**Admin Pages:**
- All require `$_SESSION['admin']` to be set
- Redirect to `/admin/login.php` if not authenticated

**Employee Pages:**
- All require `$_SESSION['EmployeeID']` to be set
- Redirect to `/user/login.php` if not authenticated

## GPS Enforcement

When `settings.EnforceGPS = 1`:
- Clock actions require GPS coordinates
- Stored in `timepunches` table: `LatitudeIN`, `LongitudeIN`, etc. (8 decimal precision)
- Also captures accuracy and IP address for each action

## Audit Trail

All timesheet modifications logged in `punch_changelog`:
- `EmployeeID`, `Date`, `ChangedBy` (admin username)
- `FieldChanged`, `OldValue`, `NewValue`
- `Reason` (justification text)
- `ChangeTime` (auto-timestamp)

## Template Pattern

**Employee Pages:**
- Include `/user/header.php` (DB connection, session check, avatar)
- Include `/user/footer.php` (closes divs, JS, docs links)

**Admin Pages:**
- Each page independently requires session check and `/auth/db.php`
- No shared header/footer templates

## Error Handling

Redirect pattern:
```php
header('Location: ../error.php?code=500&message=' . urlencode('Error description'));
```

`error.php` renders styled error page with code and message.
