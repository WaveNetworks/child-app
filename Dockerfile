FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip curl \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    libmagickwand-dev --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql zip gd \
    && pecl install imagick && docker-php-ext-enable imagick \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Docker-specific config files
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/generate-config.php /usr/local/bin/generate-config.php
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/public_html

# Copy source code into image — complete filesystem isolation.
# Your working directory is never mounted or modified.
COPY --from=admin . /var/www/public_html/admin/
COPY . /var/www/public_html/child-app/

# Install admin composer dependencies at build time
RUN cd /var/www/public_html/admin && composer install --no-dev --optimize-autoloader

# Ensure config and install dirs are writable by www-data (installer writes here)
RUN chown -R www-data:www-data \
    /var/www/public_html/admin/config \
    /var/www/public_html/admin/install \
    /var/www/public_html/child-app/config

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
