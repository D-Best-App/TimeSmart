#!/bin/bash
#
# D-BEST TimeSmart Backup Script
# Backs up all TimeSmart databases
#
# This script can be run manually or scheduled via cron:
#   crontab -e
#   0 * * * * /path/to/backup.sh  # Run every hour
#

set -e

# ========================
# Configuration
# ========================
# Database credentials - MODIFY THESE FOR YOUR ENVIRONMENT
DB_USER="${DB_USER:-timeclock}"
DB_PASS="${DB_PASS:-YOUR_PASSWORD_HERE}"
DB_HOST="${DB_HOST:-172.17.0.1}"

# Backup location - where to store backups
BACKUP_BASE="${BACKUP_BASE:-/var/sql-data}"

# How many hourly backups to keep
HOURLY_RETENTION=8

# Color codes (disabled if not in terminal)
if [ -t 1 ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    NC='\033[0m'
else
    RED=''
    GREEN=''
    YELLOW=''
    BLUE=''
    NC=''
fi

# ========================
# Functions
# ========================
log_info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] ✓${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ✗${NC} $1" >&2
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] ⚠${NC} $1"
}

# ========================
# Validation
# ========================
if [ "$DB_PASS" = "YOUR_PASSWORD_HERE" ]; then
    log_error "Please configure database credentials in this script"
    exit 1
fi

if ! command -v mysqldump &> /dev/null; then
    log_error "mysqldump not found. Please install MySQL client."
    exit 1
fi

# Test database connection
if ! mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1" &> /dev/null; then
    log_error "Failed to connect to database at $DB_HOST"
    exit 1
fi

log_info "Starting backup process..."

# ========================
# Backup Process
# ========================
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
HOUR=$(date +"%H")
BACKUP_COUNT=0
ERROR_COUNT=0

# Get list of timeclock databases
DATABASES=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES LIKE 'timeclock-%'" -N)

if [ -z "$DATABASES" ]; then
    log_warning "No timeclock-* databases found"
    exit 0
fi

for DB_NAME in $DATABASES; do
    # Extract company name from database name (timeclock-CompanyName -> CompanyName)
    COMPANY_NAME=$(echo "$DB_NAME" | sed 's/^timeclock-//')
    
    # Create backup directories
    BACKUP_DIR="$BACKUP_BASE/$COMPANY_NAME"
    DAILY_BACKUP_DIR="$BACKUP_DIR/daily-backup"
    
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$DAILY_BACKUP_DIR"
    
    # Backup filename
    BACKUP_FILE="$BACKUP_DIR/${DB_NAME}-${DATE}.sql"
    
    log_info "Backing up $DB_NAME..."
    
    # Perform backup
    if mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null; then
        
        # Compress backup
        gzip "$BACKUP_FILE"
        BACKUP_FILE="${BACKUP_FILE}.gz"
        
        SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        log_success "Backed up $DB_NAME → $BACKUP_FILE ($SIZE)"
        
        ((BACKUP_COUNT++))
        
        # Rotate hourly backups (keep last N)
        HOURLY_BACKUPS=$(ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | wc -l)
        if [ "$HOURLY_BACKUPS" -gt "$HOURLY_RETENTION" ]; then
            OLD_BACKUPS=$(ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +$((HOURLY_RETENTION + 1)))
            echo "$OLD_BACKUPS" | xargs rm -f
            log_info "Rotated old backups for $COMPANY_NAME"
        fi
        
        # Daily backup at 8 PM (20:00)
        if [ "$HOUR" -eq 20 ]; then
            cp "$BACKUP_FILE" "$DAILY_BACKUP_DIR/"
            log_success "Created daily backup for $COMPANY_NAME"
        fi
        
    else
        log_error "Failed to backup $DB_NAME"
        ((ERROR_COUNT++))
    fi
done

# ========================
# Summary
# ========================
echo ""
log_info "========================================"
if [ $ERROR_COUNT -eq 0 ]; then
    log_success "Backup complete: $BACKUP_COUNT database(s) backed up"
else
    log_warning "Backup complete: $BACKUP_COUNT succeeded, $ERROR_COUNT failed"
fi
log_info "Backups stored in: $BACKUP_BASE"
log_info "========================================"

exit $ERROR_COUNT
