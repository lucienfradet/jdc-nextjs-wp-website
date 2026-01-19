#!/bin/bash
set -e

echo "Starting Alpine-based cron container..."

# Create backup directories if they don't exist
mkdir -p /backups/temp /backups/encrypted

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

umask 077

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="/backups/temp/jdc-wp-db_${TIMESTAMP}.sql"
COMPRESSED_FILE="/backups/temp/jdc-wp-db_${TIMESTAMP}.sql.xz"
ENCRYPTED_FILE="/backups/encrypted/jdc-wp-db_${TIMESTAMP}.sql.xz.gpg"

echo "Starting WordPress database backup at $(date)"

# Simple approach - use environment variable
export MYSQL_PWD="${MYSQL_WORDPRESS_ROOT_PASSWORD}"

mysqldump -h jdc-wp-db -u root \
    --add-drop-table \
    --single-transaction \
    --routines \
    --triggers \
    jdc_db > "${BACKUP_FILE}"

# Compress with xz
xz -9 -c "${BACKUP_FILE}" > "${COMPRESSED_FILE}"

# Encrypt with GPG
gpg --batch --yes --trust-model always --encrypt -r admin@jardindeschefs.ca --output "${ENCRYPTED_FILE}" "${COMPRESSED_FILE}"

# Upload to Nextcloud
echo "Uploading to Nextcloud..."
curl -u "${NEXTCLOUD_USER}:${NEXTCLOUD_PASSWORD}" \
     -T "${ENCRYPTED_FILE}" \
     "${NEXTCLOUD_URL}/remote.php/dav/files/${NEXTCLOUD_USER}/jdc-server/jdc-wp-db/jdc-wp-db_${TIMESTAMP}.sql.xz.gpg"

# Clean up
rm -f "${BACKUP_FILE}" "${COMPRESSED_FILE}"

echo "WordPress database backup completed at $(date)"
EOL

# Create the backup script for WordPress uploads (wp-content/uploads)
cat > /usr/local/bin/backup-wp-uploads.sh << 'EOL'
#!/bin/bash
set -e

# Set restrictive permissions for all created files
umask 077

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="/backups/temp/jdc-wp-uploads_${TIMESTAMP}.tar"
COMPRESSED_FILE="/backups/temp/jdc-wp-uploads_${TIMESTAMP}.tar.xz"
ENCRYPTED_FILE="/backups/encrypted/jdc-wp-uploads_${TIMESTAMP}.tar.xz.gpg"

echo "Starting WordPress uploads backup at $(date)"

# Create tar archive of wp-content/uploads
# Adjust the path based on your WordPress installation location
# Common paths: /var/www/html/wp-content/uploads or /wordpress/wp-content/uploads
tar -cf "${BACKUP_FILE}" -C /wordpress/wp-content uploads

# Compress with xz (best compression)
xz -9 -c "${BACKUP_FILE}" > "${COMPRESSED_FILE}"

# Encrypt with GPG
gpg --batch --yes --trust-model always --encrypt -r admin@jardindeschefs.ca --output "${ENCRYPTED_FILE}" "${COMPRESSED_FILE}"

# Upload to Nextcloud
echo "Uploading to Nextcloud..."
curl -u "${NEXTCLOUD_USER}:${NEXTCLOUD_PASSWORD}" \
     -T "${ENCRYPTED_FILE}" \
     "${NEXTCLOUD_URL}/remote.php/dav/files/${NEXTCLOUD_USER}/jdc-server/jdc-wp-uploads/jdc-wp-uploads_${TIMESTAMP}.tar.xz.gpg"

# Clean up temp files after successful upload
rm -f "${BACKUP_FILE}" "${COMPRESSED_FILE}"

echo "WordPress uploads backup completed at $(date)"
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
chmod +x /usr/local/bin/backup-wp-uploads.sh
chmod +x /usr/local/bin/cleanup-old-backups.sh

# Setup cron jobs (Debian)
echo "Setting up cron jobs Debian..."

# Create cron job file in /etc/cron.d/ (Debian style)
cat > /etc/cron.d/backup-jobs << EOF
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

# Database backup - daily at 2 AM EST (UTC+4)  
0 6 * * * root /usr/local/bin/backup-wp-db.sh >> /proc/1/fd/1 2>&1

# WordPress uploads backup - weekly on Sunday at 2:15 AM EST (UTC+4)
15 6 * * 0 root /usr/local/bin/backup-wp-uploads.sh >> /proc/1/fd/1 2>&1

# Cleanup old backups weekly on Sunday at 3 AM EST
0 7 * * 0 root /usr/local/bin/cleanup-old-backups.sh >> /proc/1/fd/1 2>&1

EOF

# Set proper permissions for the cron file
chmod 644 /etc/cron.d/backup-jobs

echo "Cron jobs configured"

# Start cron daemon in foreground (Debian style)
echo "Starting cron daemon..."
exec cron -f
