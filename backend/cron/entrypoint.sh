#!/bin/bash
set -e

echo "Starting Alpine-based cron container..."

# Import the public GPG key for encryption
echo "Importing GPG public key..."
if [ -f /keys/jdc-backup-public.key ]; then
    gpg --import /keys/jdc-backup-public.key
    echo "GPG public key imported successfully"
else
    echo "Warning: GPG public key not found at /keys/jdc-backup-public.key"
fi

# Create the backup script for WordPress database
cat > /usr/local/bin/backup-wp-db.sh << 'EOL'
#!/bin/bash
set -e

# Set restrictive permissions for all created files
umask 077

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="/backups/temp/jdc-wp-db_${TIMESTAMP}.sql"
COMPRESSED_FILE="/backups/temp/jdc-wp-db_${TIMESTAMP}.sql.xz"
ENCRYPTED_FILE="/backups/encrypted/jdc-wp-db_${TIMESTAMP}.sql.xz.gpg"

echo "Starting WordPress database backup at $(date)"

# Set password as environment variable (MySQL standard approach)
export MYSQL_PWD="${MYSQL_WORDPRESS_ROOT_PASSWORD}"

# Create database dump using MySQL client
mysqldump -h jdc-wp-db -u root \
    --single-transaction --routines --triggers \
    jdc_db > "${BACKUP_FILE}"

# Compress with xz (best compression)
xz -9 -c "${BACKUP_FILE}" > "${COMPRESSED_FILE}"

# Encrypt with GPG
gpg --batch --yes --trust-model always --encrypt -r admin@jardindeschefs.ca --output "${ENCRYPTED_FILE}" "${COMPRESSED_FILE}"

# Upload to Nextcloud
echo "Uploading to Nextcloud..."
curl -u "${NEXTCLOUD_USER}:${NEXTCLOUD_PASSWORD}" \
     -T "${ENCRYPTED_FILE}" \
     "${NEXTCLOUD_URL}/remote.php/dav/files/lucienfradet/backup/jdc-server/jdc-wp-db/jdc-wp-db_${TIMESTAMP}.sql.xz.gpg"

# Clean up temp files after successful upload
rm -f "${BACKUP_FILE}" "${COMPRESSED_FILE}"

echo "WordPress database backup completed at $(date)"
EOL

# Create the backup script for Orders database  
# THIS ACTUALLY DOESNT GET BACKED UP!
# THE TEMPORARY LOCAL DB IS NOT BACKUP UP!
# ALSO, ROOT PERMISSIONS DEFINED IN DOCKER-COMPOSE ENTRY SCRIPT PREVENT 
# CONNECTION FROM OTHER DOCKERS!! (TO SET BACKUPS, WE NEED TO CHANGE THE PERMS!)
cat > /usr/local/bin/backup-orders-db.sh << 'EOL'
#!/bin/bash
set -e

# Set restrictive permissions for all created files
umask 077

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="/backups/temp/jdc-orders-db_${TIMESTAMP}.sql"
COMPRESSED_FILE="/backups/temp/jdc-orders-db_${TIMESTAMP}.sql.xz"
ENCRYPTED_FILE="/backups/encrypted/jdc-orders-db_${TIMESTAMP}.sql.xz.gpg"

echo "Starting Orders database backup at $(date)"

# Set password as environment variable (MySQL standard approach)
export MYSQL_PWD="${MYSQL_NEXTJS_ROOT_PASSWORD}"

# Create database dump using MySQL client
mysqldump -h jdc-orders-db -u root \
    --single-transaction --routines --triggers \
    ${MYSQL_NEXTJS_DATABASE} > "${BACKUP_FILE}"

# Compress with xz (best compression)
xz -9 -c "${BACKUP_FILE}" > "${COMPRESSED_FILE}"

# Encrypt with GPG
gpg --batch --yes --trust-model always --encrypt -r admin@jardindeschefs.ca --output "${ENCRYPTED_FILE}" "${COMPRESSED_FILE}"

# Upload to Nextcloud
echo "Uploading to Nextcloud..."
curl -u "${NEXTCLOUD_USER}:${NEXTCLOUD_PASSWORD}" \
     -T "${ENCRYPTED_FILE}" \
     "${NEXTCLOUD_URL}/remote.php/dav/files/lucienfradet/backup/jdc-server/jdc-orders-db/jdc-orders-db_${TIMESTAMP}.sql.xz.gpg"

# Clean up temp files after successful upload
rm -f "${BACKUP_FILE}" "${COMPRESSED_FILE}"

echo "Orders database backup completed at $(date)"
EOL

# Create cleanup script to remove old local backups
cat > /usr/local/bin/cleanup-old-backups.sh << 'EOL'
#!/bin/bash
# Remove encrypted backups older than 7 days
find /backups/encrypted -name "*.gpg" -mtime +7 -delete
echo "Cleaned up old backup files"
EOL

# Make scripts executable
chmod +x /usr/local/bin/backup-wp-db.sh
chmod +x /usr/local/bin/backup-orders-db.sh  
chmod +x /usr/local/bin/cleanup-old-backups.sh

# Setup cron jobs (Alpine)
# echo "Setting up cron jobs Alpine..."
# cat > /etc/crontabs/root << 'EOL'
# # Run cleanup job every hour
# 0 * * * * curl -s -X GET -H "x-api-key: ${CRON_SECRET_KEY}" ${TRAEFIK_URL}/api/cron/cleanup-expired-intents >> /proc/1/fd/1 2>&1
#
# # Database backups - daily at 2 AM EST (UTC+4)
# 0 6 * * * /usr/local/bin/backup-wp-db.sh >> /proc/1/fd/1 2>&1
# 30 6 * * * /usr/local/bin/backup-orders-db.sh >> /proc/1/fd/1 2>&1
#
# # Cleanup old backups weekly on Sunday at 3 AM EST
# 0 7 * * 0 /usr/local/bin/cleanup-old-backups.sh >> /proc/1/fd/1 2>&1
#
# EOL

# Setup cron jobs (Debian)
echo "Setting up cron jobs Debian..."

# Create cron job file in /etc/cron.d/ (Debian style)
cat > /etc/cron.d/backup-jobs << 'EOL'
# Set environment variables for cron jobs
CRON_SECRET_KEY=${CRON_SECRET_KEY}
TRAEFIK_URL=${TRAEFIK_URL}
MYSQL_WORDPRESS_ROOT_PASSWORD=${MYSQL_WORDPRESS_ROOT_PASSWORD}
MYSQL_NEXTJS_ROOT_PASSWORD=${MYSQL_NEXTJS_ROOT_PASSWORD}
MYSQL_NEXTJS_DATABASE=${MYSQL_NEXTJS_DATABASE}
NEXTCLOUD_URL=${NEXTCLOUD_URL}
NEXTCLOUD_USER=${NEXTCLOUD_USER}
NEXTCLOUD_PASSWORD=${NEXTCLOUD_PASSWORD}

# Run cleanup job every hour
0 * * * * root curl -s -X GET -H "x-api-key: ${CRON_SECRET_KEY}" ${TRAEFIK_URL}/api/cron/cleanup-expired-intents >> /proc/1/fd/1 2>&1

# Database backups - daily at 2 AM EST (UTC+4)  
0 6 * * * root /usr/local/bin/backup-wp-db.sh >> /proc/1/fd/1 2>&1
# 30 6 * * * root /usr/local/bin/backup-orders-db.sh >> /proc/1/fd/1 2>&1

# Cleanup old backups weekly on Sunday at 3 AM EST
0 7 * * 0 root /usr/local/bin/cleanup-old-backups.sh >> /proc/1/fd/1 2>&1

EOL

# Set proper permissions for the cron file
chmod 644 /etc/cron.d/backup-jobs

echo "Cron jobs configured"

# Start crond in foreground (Alpine)
# echo "Starting crond..."
# crond -f -d 8

# Start cron daemon in foreground (Debian style)
echo "Starting cron daemon..."
exec cron -f
