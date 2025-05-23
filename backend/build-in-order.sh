#!/bin/bash

# Set proper permissions for WordPress
WP_DIR="./wordpress"
WP_CONTENT="${WP_DIR}/wp-content"

echo "Setting WordPress permissions..."
sudo chown -R www-data:www-data "${WP_DIR}"
sudo find "${WP_DIR}" -type d -exec chmod 755 {} \;
sudo find "${WP_DIR}" -type f -exec chmod 644 {} \;

# Special permissions for uploads
UPLOADS_DIR="${WP_CONTENT}/uploads"
sudo chown -R www-data:www-data "${UPLOADS_DIR}"
sudo chmod -R 775 "${UPLOADS_DIR}"

echo "Permissions updated for WordPress and Nginx"

echo "Starting WordPress dependencies..."
docker compose -f docker-compose.yml up -d db wordpress

echo "Waiting for WordPress to be healthy..."
docker compose -f docker-compose.yml exec wordpress bash -c 'while ! curl -f http://localhost/wp-admin/install.php >/dev/null 2>&1; do sleep 2; done'

echo "WordPress is ready! Building Next.js..."
docker compose -f docker-compose.yml -f docker-compose.prod.yml build nextjs

echo "Starting all services..."
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
