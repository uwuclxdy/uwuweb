#!/bin/bash
# filepath: /home/uwuclxdy/PhpstormProjects/uwuweb/db/migrate_system_settings.sh

# Database migration script for the system_settings table

# Configuration
DB_USER="root"
DB_PASS=""
DB_NAME="uwuweb"
SQL_FILE="system_settings.sql"

# Colorized output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting database migration for system_settings table...${NC}"

# Check if the SQL file exists
if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}Error: SQL file $SQL_FILE not found${NC}"
    exit 1
fi

# Execute the SQL script
echo "Applying migration..."
sudo /opt/lampp/bin/mysql -u "$DB_USER" --database="$DB_NAME" < "$SQL_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}Migration successful!${NC}"
else
    echo -e "${RED}Migration failed. Please check the error message above.${NC}"
    exit 1
fi

echo "Done."
exit 0
