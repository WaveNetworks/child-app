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

# Source code is bind-mounted at runtime via docker-compose.yml.
# No COPY of application source — edit files on the host and refresh.

# Create files directory below the docroot (not web-accessible)
RUN mkdir -p /var/www/files/home /var/www/files/branding

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
