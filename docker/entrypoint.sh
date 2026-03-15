#!/bin/bash
set -e

# Ensure files directory exists
mkdir -p "${FILES_LOCATION:-/var/files}/home"
chown -R www-data:www-data "${FILES_LOCATION:-/var/files}"

# Install admin's composer deps if vendor/ is missing (first run after volume mount)
if [ -f /var/www/public_html/admin/composer.json ] && [ ! -d /var/www/public_html/admin/vendor ]; then
    echo "Installing admin composer dependencies..."
    cd /var/www/public_html/admin && composer install --no-dev --optimize-autoloader
fi

# Start Apache
exec "$@"
