#!/bin/sh
set -e

cd /var/www/html

echo "Checking Laravel database configuration..."

if [ "$DB_CONNECTION" != "pgsql" ] || [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ]; then
    echo "ERROR: PostgreSQL environment variables are missing or incorrect."
    exit 1
fi

echo "Checking pending database migrations..."
php artisan migrate:status || true

echo "Running pending migrations on Render PostgreSQL..."
php artisan migrate --force

echo "Starting Apache..."
exec apache2-foreground
