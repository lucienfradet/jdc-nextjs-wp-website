#!/bin/sh
set -e

# Install curl
apk add --no-cache curl

# Setup cron job
echo "Setting up cron job..."
echo "# Run cleanup job every hour" > /etc/crontabs/root
echo "0 * * * * curl -s -X GET -H \"x-api-key: ${CRON_SECRET_KEY}\" ${NEXTJS_URL}/api/cron/cleanup-expired-intents >> /var/log/cron.log 2>&1" >> /etc/crontabs/root
echo "" >> /etc/crontabs/root  # Empty line required at end

# Make sure log file exists
touch /var/log/cron.log

# Start crond in foreground
echo "Starting crond..."
crond -f -d 8
