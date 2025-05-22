#!/bin/bash

echo "Starting WordPress dependencies..."
docker compose -f docker-compose.yml up -d db wordpress

echo "Waiting for WordPress to be healthy..."
docker compose -f docker-compose.yml exec wordpress bash -c 'while ! curl -f http://localhost/wp-admin/install.php >/dev/null 2>&1; do sleep 2; done'

echo "WordPress is ready! Building Next.js..."
docker compose -f docker-compose.yml -f docker-compose.prod.yml build nextjs

echo "Starting all services..."
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
