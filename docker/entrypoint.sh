#!/bin/bash
set -e

# Ensure files directory exists
mkdir -p "${FILES_LOCATION:-/var/files}/home" "${FILES_LOCATION:-/var/files}/branding"
chown -R www-data:www-data "${FILES_LOCATION:-/var/files}"

# Generate admin config.php from env vars if not present (first run only)
if [ ! -f /var/www/public_html/admin/config/config.php ] && [ -n "$DB_HOST_MAIN" ]; then
    php /usr/local/bin/generate-config.php
    echo "Generated admin config.php from environment variables."
fi

# Start Apache
exec "$@"
