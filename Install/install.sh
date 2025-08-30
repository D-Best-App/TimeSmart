#!/bin/bash

# Ask for the company name
read -p "Enter the company name: " company_name

# Create a directory for the company
mkdir "Timeclock-$company_name"
cd "Timeclock-$company_name"

# Clone the repository
echo "Cloning the repository..."
git clone https://github.com/D-Best-App/Timesmart.git .

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
    sed -i "s/COMPANY_NAME_PLACEHOLDER/Timeclock-$company_name/g" Install/docker-compose.yml
    sed -i "s/DB_HOST_PLACEHOLDER/$db_host/g" Install/docker-compose.yml
    sed -i "s/DB_NAME_PLACEHOLDER/timeclock-$company_name/g" Install/docker-compose.yml
    sed -i "s/DB_USER_PLACEHOLDER/$db_user/g" Install/docker-compose.yml
    sed -i "s/DB_PASS_PLACEHOLDER/$db_pass/g" Install/docker-compose.yml
    docker-compose -f Install/docker-compose.yml up -d --build
fi

# Ask to create database
read -p "Create database named 'timeclock-$company_name'? (y/n): " create_database
if [ "$create_database" == "y" ]; then
    echo "Creating database..."

    sed "s/DB_NAME_PLACEHOLDER/timeclock-$company_name/g" Install/timeclock-schema.sql > Install/timeclock-schema-temp.sql
    mysql -h "$db_host" -u "$db_user" -p"$db_pass" < Install/timeclock-schema-temp.sql
    rm Install/timeclock-schema-temp.sql
fi

echo "Installation complete."