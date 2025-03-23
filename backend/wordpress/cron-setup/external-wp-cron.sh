#!/bin/bash
# External WordPress Cron Script
# This is meant as a backup plan (STILL UNTESTED) if everything else fails
# Save this on your host system and make it executable with: chmod +x run-wp-cron.sh
# Then set up a cron job to run it: crontab -e
# Add: */15 * * * * /path/to/run-wp-cron.sh

# Configuration
WP_CONTAINER="jdc-wp-app"
LOG_FILE="/var/log/wp-cron.log"

# Get timestamp
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")

# Create log file if it doesn't exist
if [ ! -f "$LOG_FILE" ]; then
    touch "$LOG_FILE"
    echo "WordPress cron log file created at $TIMESTAMP" >> "$LOG_FILE"
fi

echo "[$TIMESTAMP] Running WordPress cron..." >> "$LOG_FILE"

# Method 1: Use WP-CLI inside the container (preferred method)
if docker exec -t "$WP_CONTAINER" which wp > /dev/null 2>&1; then
    echo "[$TIMESTAMP] Using WP-CLI method..." >> "$LOG_FILE"
    docker exec -t "$WP_CONTAINER" wp cron event run --due-now --allow-root >> "$LOG_FILE" 2>&1
    WP_CLI_EXIT=$?
    
    if [ $WP_CLI_EXIT -eq 0 ]; then
        echo "[$TIMESTAMP] WP-CLI cron execution successful" >> "$LOG_FILE"
        exit 0
    else
        echo "[$TIMESTAMP] WP-CLI method failed with exit code $WP_CLI_EXIT, trying fallback method..." >> "$LOG_FILE"
    fi
fi

# Method 2: Execute the internal script we created in the entrypoint
echo "[$TIMESTAMP] Using internal script method..." >> "$LOG_FILE"
docker exec -t "$WP_CONTAINER" /usr/local/bin/run-wp-cron >> "$LOG_FILE" 2>&1
SCRIPT_EXIT=$?

if [ $SCRIPT_EXIT -eq 0 ]; then
    echo "[$TIMESTAMP] Internal script cron execution successful" >> "$LOG_FILE"
    exit 0
else
    echo "[$TIMESTAMP] Internal script method failed with exit code $SCRIPT_EXIT, trying direct curl..." >> "$LOG_FILE"
fi

# Method 3: Direct curl to container (last resort fallback)
echo "[$TIMESTAMP] Using direct curl method..." >> "$LOG_FILE"
docker exec -t "$WP_CONTAINER" curl -s "http://jdc-wp-app/wp-cron.php?doing_wp_cron" >> "$LOG_FILE" 2>&1
CURL_EXIT=$?

if [ $CURL_EXIT -eq 0 ]; then
    echo "[$TIMESTAMP] Direct curl cron execution successful" >> "$LOG_FILE"
    exit 0
else
    echo "[$TIMESTAMP] All cron methods failed! Check WordPress configuration." >> "$LOG_FILE"
    exit 1
fi
