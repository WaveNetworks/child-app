#!/bin/bash
set -e

# Ensure files directory exists (below docroot for security)
mkdir -p /var/www/files/home /var/www/files/branding
chown -R www-data:www-data /var/www/files

# Ensure config dirs are writable by www-data (bind-mounted from host)
chown -R www-data:www-data \
    /var/www/public_html/admin/config \
    /var/www/public_html/admin/install \
    /var/www/public_html/elevateher/config

# Install admin composer dependencies if vendor/ is missing
if [ ! -d /var/www/public_html/admin/vendor ]; then
    echo "Installing admin composer dependencies..."
    cd /var/www/public_html/admin && composer install --no-dev --optimize-autoloader
fi

# Generate admin config.php from env vars if not present (first run only)
if [ ! -f /var/www/public_html/admin/config/config.php ] && [ -n "$DB_HOST_MAIN" ]; then
    php /usr/local/bin/generate-config.php
    echo "Generated admin config.php from environment variables."
fi

# Generate ElevateHER child app config from env vars if not present
if [ ! -f /var/www/public_html/elevateher/config/config.php ] && [ -n "$CHILD_DB_HOST" ]; then
    php /usr/local/bin/generate-elevateher-config.php
    echo "Generated elevateher config.php from environment variables."
fi

# Start Apache
exec "$@"
