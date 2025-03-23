#!/bin/bash
set -e

# Fix hosts resolution
echo "127.0.0.1 jdc-wp-app localhost" >> /etc/hosts
echo "Configured local hostname resolution"

# Install necessary packages
echo "Installing required packages..."
apt-get update && apt-get -y install cron wget curl sudo less

# Install WP-CLI for better WordPress management
if [ ! -f /usr/local/bin/wp ]; then
    echo "Installing WP-CLI..."
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
    # Create wp-cli config
    mkdir -p /var/www/.wp-cli
    echo "path: /var/www/html" > /var/www/.wp-cli/config.yml
    chown -R www-data:www-data /var/www/.wp-cli
fi

# Set up proper cron job for WP-CLI approach (more reliable)
echo "Setting up WordPress cron job..."
cat > /etc/cron.d/wordpress-cron << 'EOL'
# Run WordPress cron every 5 minutes
*/5 * * * * www-data cd /var/www/html && /usr/local/bin/wp cron event run --due-now --allow-root > /dev/null 2>&1
EOL

# Alternative approach using curl as fallback
cat > /etc/cron.d/wordpress-cron-fallback << 'EOL'
# Fallback cron using direct HTTP call (only runs if 10 minutes pass)
*/10 * * * * www-data curl -s "http://jdc-wp-app/wp-cron.php?doing_wp_cron" > /dev/null 2>&1
EOL

# Set proper permissions
chmod 0644 /etc/cron.d/wordpress-cron
chmod 0644 /etc/cron.d/wordpress-cron-fallback

# Start cron service
service cron start
echo "Cron service started"

# Create a script for external cron execution
cat > /usr/local/bin/run-wp-cron << 'EOL'
#!/bin/bash
# Change to WordPress directory
cd /var/www/html

# Try WP-CLI approach first (most reliable)
if command -v wp &> /dev/null; then
    echo "Running WordPress cron events via WP-CLI..."
    wp cron event run --due-now --allow-root
else
    # Fallback to direct HTTP approach
    echo "Running WordPress cron via HTTP request..."
    curl -s "http://jdc-wp-app/wp-cron.php?doing_wp_cron"
fi
EOL

chmod +x /usr/local/bin/run-wp-cron
echo "Created external cron script at /usr/local/bin/run-wp-cron"

# Execute the original entrypoint with all arguments or with a default if none provided
echo "Starting WordPress..."
if [ $# -eq 0 ]; then
    exec docker-entrypoint.sh apache2-foreground
else
    exec docker-entrypoint.sh "$@"
fi
