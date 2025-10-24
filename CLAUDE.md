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

This application runs in Docker with **volume-mounted** application files for easy development.

```bash
# Navigate to installation directory
cd /opt/Timeclock-<CompanyName>

# Update application (git pull + container restart)
./deploy/scripts/update.sh

# Find container IP address
docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' <container_name>

# Access container shell
docker exec -it <container_name> /bin/bash

# View logs
docker logs <container_name>

# Restart container (applies config changes)
docker restart <container_name>

# Restart services inside container
docker exec <container_name> supervisorctl restart all
```

**Development Workflow:**
1. Edit files in `app/` directory on host
2. Changes appear immediately in browser (no rebuild needed!)
3. Use `docker restart` only if changing config files

### Database Operations

```bash
# Connect to database (from host)
mysql -h <db_host> -u <db_user> -p<db_pass> <db_name>

# Import schema
mysql -h <db_host> -u <db_user> -p<db_pass> <db_name> < deploy/database/schema.sql

# Backup database (manual)
mysqldump -h <db_host> -u <db_user> -p<db_pass> <db_name> > backup.sql

# Backup database (automated script)
./deploy/scripts/backup.sh
```

### Composer Dependencies

```bash
# Install dependencies
cd app/
composer install

# Update dependencies
composer update

# Required packages:
# - phpoffice/phpspreadsheet (Excel export)
# - spomky-labs/otphp (2FA TOTP)
# - endroid/qr-code (QR code generation)
# - vlucas/phpdotenv (environment variables)
```

### Installation & Deployment

```bash
# New installation (interactive)
bash <(curl -s https://raw.githubusercontent.com/D-Best-App/Timesmart/main/deploy/scripts/install.sh)

# Update existing installation
cd /opt/Timeclock-<CompanyName>
./deploy/scripts/update.sh

# Backup databases
./deploy/scripts/backup.sh

# Manual installation
git clone https://github.com/D-Best-App/Timesmart.git
cd Timesmart
# Edit docker-compose.yml with your settings
docker compose up -d
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

**New Consolidated Structure:**

```
Timeclock-<CompanyName>/
├── app/                      # Application code (volume-mounted to /var/www/html)
│   ├── admin/               # Admin portal (22 files): manage_users.php, reports.php, etc.
│   ├── user/                # Employee portal (10 files): dashboard.php, timesheet.php, etc.
│   ├── kiosk/               # Badge scanning interface with audio feedback
│   ├── functions/           # Reusable utilities:
│   │   ├── clock_action.php (497 LOC) - Core punch/clock logic
│   │   ├── clock_handler.php - PIN verification (⚠️ SQL injection vulnerability)
│   │   ├── get_setting.php - Settings retrieval
│   │   └── verify_pin.php - PIN validation
│   ├── auth/                # db.php - Database connection via mysqli with timezone setting
│   ├── css/                 # 26 stylesheet files
│   ├── js/                  # 13 JavaScript files including kiosk logic
│   ├── images/              # Static assets
│   ├── vendor/              # Composer dependencies
│   ├── index.php            # Public dashboard
│   ├── error.php            # Error page handler
│   ├── composer.json        # PHP dependencies
│   └── privacy.php, terms.php, report.php, etc.
│
├── deploy/                  # Deployment configuration (consolidated from Install/)
│   ├── docker/
│   │   ├── Dockerfile       # PHP 8.3-FPM + Nginx + Supervisor
│   │   ├── nginx.conf       # Nginx configuration with Cloudflare real IP
│   │   ├── supervisord.conf # Process management (PHP-FPM + Nginx)
│   │   └── www.conf         # PHP-FPM pool configuration
│   ├── database/
│   │   ├── schema.sql       # Database schema with 8 tables
│   │   └── create-timeclock-user.sql
│   └── scripts/
│       ├── install.sh       # Interactive installation (git clone + docker + db)
│       ├── update.sh        # Update from git and restart container
│       ├── backup.sh        # Automated database backup script
│       └── remove.sh        # Cleanup script
│
├── docs/                    # Documentation
│   ├── INSTALLATION.md      # Step-by-step installation guide
│   ├── DEPLOYMENT.md        # Update, backup, maintenance procedures
│   ├── CONFIGURATION.md     # Configuration reference (env vars, PHP, Nginx, DB)
│   └── README.md            # User documentation
│
├── docker-compose.yml       # Container orchestration with volume mounts
├── CLAUDE.md                # This file - developer documentation
├── README.md                # Project overview
└── CHANGELOG.md             # Version history
```

**Key Changes from Old Structure:**
- ✅ All application code in `app/` (was scattered in root)
- ✅ All deployment files in `deploy/` (was `/Install/`)
- ✅ Volume mounts allow live development (no rebuilds needed)
- ✅ Comprehensive documentation in `docs/`
- ✅ Scripts for install, update, backup in `deploy/scripts/`

### Configuration Management

**Environment Variables (docker-compose.yml):**
- `DB_HOST` - Database server hostname (default: `172.17.0.1`)
- `DB_NAME` - Database name (format: `timeclock-companyname`)
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_TIMEZONE` - Timezone (default: `America/Chicago`)

Accessed in `/app/auth/db.php` via `$_ENV` superglobal. Set in `docker-compose.yml`:

```yaml
environment:
  DB_HOST: 172.17.0.1
  DB_NAME: timeclock-acme
  DB_USER: timeclock
  DB_PASS: secure_password
  DB_TIMEZONE: America/Chicago
```

**Runtime Settings Table:**
- `EnforceGPS` - Require GPS on clock actions (0/1)
- Queried via `/app/functions/get_setting.php`

**Configuration Files:**
- `docker-compose.yml` - Container config + environment variables
- `deploy/docker/nginx.conf` - Nginx web server config
- `deploy/docker/www.conf` - PHP-FPM pool config
- `deploy/docker/supervisord.conf` - Process management
- See [CONFIGURATION.md](docs/CONFIGURATION.md) for full reference

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

## Development Workflow

### Making Changes

With volume mounts, development is streamlined:

1. **Edit files** in `app/` on host machine
2. **Changes appear immediately** in browser (no rebuild!)
3. **Test** in browser
4. **Commit** when satisfied

```bash
cd /opt/Timeclock-YourCompany

# Edit files
nano app/admin/dashboard.php

# Test in browser (refresh page)

# Commit changes
git add app/admin/dashboard.php
git commit -m "Update dashboard UI"
git push
```

### No More docker cp!

**Old workflow (painful):**
```bash
# Edit file
nano /path/to/file.php
# Copy to container
docker cp file.php container:/var/www/html/
# Restart container
docker restart container
```

**New workflow (easy):**
```bash
# Edit file
nano app/file.php
# Refresh browser - done!
```

### Updating Production

```bash
# On production server
cd /opt/Timeclock-YourCompany
./deploy/scripts/update.sh

# Script will:
# 1. Backup database (prompt)
# 2. Git pull latest changes
# 3. Update composer if needed
# 4. Restart container
# 5. Show success message
```

### Configuration Changes

Config files require container restart:

```bash
# Edit nginx.conf
nano deploy/docker/nginx.conf

# Restart to apply
docker restart Timeclock-YourCompany
```

## Documentation Structure

Comprehensive documentation for all audiences:

### For Users/Administrators

1. **README.md** - Project overview, quick links
2. **docs/INSTALLATION.md** - Installation guide
   - Prerequisites
   - Quick start (automated installer)
   - Detailed manual installation
   - Multi-company setup
   - Troubleshooting

### For DevOps/System Administrators

3. **docs/DEPLOYMENT.md** - Operations guide
   - Updating TimeSmart
   - Backup and restore procedures
   - Container management
   - Production best practices
   - Monitoring and maintenance
   - Disaster recovery

4. **docs/CONFIGURATION.md** - Configuration reference
   - Environment variables
   - Docker configuration
   - PHP-FPM tuning
   - Nginx configuration
   - Database optimization
   - Security settings

### For Developers

5. **CLAUDE.md** (this file) - Developer documentation
   - Project architecture
   - Database schema
   - Security patterns
   - Known issues
   - Development workflow

## Scripts Reference

All deployment scripts in `deploy/scripts/`:

### install.sh

Interactive installation wizard:
- Checks prerequisites (Docker, Git, MySQL)
- Prompts for company name, database credentials
- Clones repository
- Configures docker-compose.yml
- Creates database (optional)
- Starts container
- Shows access information

```bash
bash <(curl -s https://raw.githubusercontent.com/D-Best-App/Timesmart/main/deploy/scripts/install.sh)
```

### update.sh

Update existing installation:
- Checks git status
- Prompts for backup confirmation
- Pulls latest changes
- Updates composer dependencies if needed
- Restarts container
- Shows what changed

```bash
cd /opt/Timeclock-YourCompany
./deploy/scripts/update.sh
```

### backup.sh

Automated database backup:
- Backs up all `timeclock-*` databases
- Compresses backups (gzip)
- Rotates hourly backups (keeps last 8)
- Creates daily backups at 8 PM
- Can be scheduled via cron

```bash
# Configure credentials in script
sudo nano deploy/scripts/backup.sh

# Run manually
sudo ./deploy/scripts/backup.sh

# Schedule via cron (hourly)
crontab -e
# Add: 0 * * * * /opt/Timeclock-YourCompany/deploy/scripts/backup.sh
```

### remove.sh

Clean removal of installation:
- Stops and removes container
- Optionally removes database
- Optionally removes files

```bash
cd /opt/Timeclock-YourCompany
./deploy/scripts/remove.sh
```

## File Reference

Quick reference to key files:

**Application Code** (`app/`):
- `admin/` - Admin portal pages
- `user/` - Employee portal pages
- `kiosk/` - Badge scanning interface
- `functions/` - Shared PHP functions
- `auth/db.php` - Database connection singleton
- `index.php` - Public status dashboard

**Deployment** (`deploy/`):
- `docker/Dockerfile` - Container image definition
- `docker/nginx.conf` - Web server config
- `docker/www.conf` - PHP-FPM config
- `docker/supervisord.conf` - Process manager
- `database/schema.sql` - Database schema
- `scripts/*.sh` - Installation/management scripts

**Configuration**:
- `docker-compose.yml` - Container orchestration + env vars
- `.gitignore` - Excluded files (vendor/, logs/, etc.)

**Documentation**:
- `README.md` - Project overview
- `CLAUDE.md` - This file (developer docs)
- `CHANGELOG.md` - Version history
- `docs/INSTALLATION.md` - Installation guide
- `docs/DEPLOYMENT.md` - Operations guide
- `docs/CONFIGURATION.md` - Config reference

## Getting Help

- **Installation Issues**: See [docs/INSTALLATION.md](docs/INSTALLATION.md)
- **Deployment/Updates**: See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- **Configuration**: See [docs/CONFIGURATION.md](docs/CONFIGURATION.md)
- **Development**: Read this file (CLAUDE.md)
- **GitHub Issues**: https://github.com/D-Best-App/Timesmart/issues
