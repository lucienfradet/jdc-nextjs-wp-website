#!/bin/bash
# MySQL backup script for DigitalOcean droplet

# Load environment variables
source /path/to/your/.env.production

# Set backup directory
BACKUP_DIR="/path/to/backups"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_DIR/daily"
mkdir -p "$BACKUP_DIR/weekly"
mkdir -p "$BACKUP_DIR/monthly"

# Create backup - do not use root password in command directly
docker exec jdc-mysql sh -c 'exec mysqldump --all-databases -uroot -p"$MYSQL_ROOT_PASSWORD"' | gzip > "$BACKUP_DIR/daily/all-db-$DATE.sql.gz"

# Copy to weekly backup on Sundays
if [ "$(date +%u)" = "7" ]; then
  cp "$BACKUP_DIR/daily/all-db-$DATE.sql.gz" "$BACKUP_DIR/weekly/all-db-$DATE.sql.gz"
fi

# Copy to monthly backup on 1st of month
if [ "$(date +%d)" = "01" ]; then
  cp "$BACKUP_DIR/daily/all-db-$DATE.sql.gz" "$BACKUP_DIR/monthly/all-db-$DATE.sql.gz"
fi

# Remove backups older than 7 days (daily), 5 weeks (weekly), 12 months (monthly)
find "$BACKUP_DIR/daily" -type f -name "*.sql.gz" -mtime +7 -delete
find "$BACKUP_DIR/weekly" -type f -name "*.sql.gz" -mtime +35 -delete
find "$BACKUP_DIR/monthly" -type f -name "*.sql.gz" -mtime +365 -delete

# Optional: Upload to external storage (S3, DigitalOcean Spaces, etc.)
# rclone copy "$BACKUP_DIR/daily/all-db-$DATE.sql.gz" remote:bucket-name/mysql-backups/
