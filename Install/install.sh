#!/bin/bash

# Check if Docker is installed and running
if ! [ -x "$(command -v docker)" ]; then
    echo "Error: Docker is not installed." >&2
    exit 1
fi

if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running." >&2
    exit 1
fi

# Ask for the company name
read -p "Enter the company name: " company_name

# Create a directory for the company
mkdir "Timeclock-$company_name"
cd "Timeclock-$company_name"

# Download necessary files from GitHub
echo "Downloading necessary files from GitHub..."
curl -L https://raw.githubusercontent.com/D-Best-App/Timesmart/main/Install/docker-compose.yml -o docker-compose.yml
curl -L https://raw.githubusercontent.com/D-Best-App/Timesmart/main/Install/timeclock-schema.sql -o timeclock-schema.sql

# Ask for database credentials
read -p "Enter database host: " db_host
read -p "Enter database user: " db_user
read -s -p "Enter database password: " db_pass
echo

# Ask to create Docker container
read -p "Create Docker container named 'Timeclock-$company_name'? (y/n): " create_docker
if [ "$create_docker" == "y" ]; then
    echo "Creating Docker container..."
    # Modify the docker-compose.yml to use the company name and database credentials
    sed -i "s/COMPANY_NAME_PLACEHOLDER/Timeclock-$company_name/g" docker-compose.yml
    sed -i "s/DB_HOST_PLACEHOLDER/$db_host/g" docker-compose.yml
    sed -i "s/DB_NAME_PLACEHOLDER/timeclock-$company_name/g" docker-compose.yml
    sed -i "s/DB_USER_PLACEHOLDER/$db_user/g" docker-compose.yml
    sed -i "s/DB_PASS_PLACEHOLDER/$db_pass/g" docker-compose.yml
    docker compose -f docker-compose.yml up -d
fi

# Ask to create database
read -p "Create database named 'timeclock-$company_name'? (y/n): " create_database
if [ "$create_database" == "y" ]; then
    echo "Creating database..."

    sed "s/DB_NAME_PLACEHOLDER/timeclock-$company_name/g" timeclock-schema.sql > timeclock-schema-temp.sql
    mysql -h "$db_host" -u "$db_user" -p"$db_pass" < timeclock-schema-temp.sql
    rm timeclock-schema-temp.sql
fi

# Ask to create backup script
read -p "Create backup script and cron job? (y/n): " create_backup
if [ "$create_backup" == "y" ]; then
    echo "Creating backup script..."

    # Create backup directories
    mkdir -p "/var/sql-data/$company_name/daily-backup"

    # Create backup script
    cat << EOF > /usr/local/bin/backup.sh
#!/bin/bash

DB_USER="$db_user"
DB_PASS="$db_pass"
DB_HOST="$db_host"
DB_NAME="timeclock-$company_name"
COMPANY_NAME="$company_name"
BACKUP_DIR="/var/sql-data/\$COMPANY_NAME"
DAILY_BACKUP_DIR="\$BACKUP_DIR/daily-backup"
DATE=\$(date +"%Y-%m-%d_%H-%M-%S")
HOUR=\$(date +"%H")

# Hourly backup
mysqldump -h"\$DB_HOST" -u"\$DB_USER" -p"\$DB_PASS" "\$DB_NAME" > "\$BACKUP_DIR/\$DB_NAME-\$DATE.sql"

# Rotate hourly backups (keep last 8)
ls -1t "\$BACKUP_DIR"/*.sql | tail -n +9 | xargs -I {} rm -- {}

# Daily backup at 8 PM (20:00)
if [ "\$HOUR" -eq 20 ]; then
    cp "\$BACKUP_DIR/\$DB_NAME-\$DATE.sql" "\$DAILY_BACKUP_DIR/\$DB_NAME-\$DATE.sql"
fi
EOF

    # Make backup script executable
    chmod +x /usr/local/bin/backup.sh

    # Add cron job
    (crontab -l 2>/dev/null; echo "0 * * * * /usr/local/bin/backup.sh") | crontab -

    echo "Backup script and cron job created."
fi

echo "Installation complete."
