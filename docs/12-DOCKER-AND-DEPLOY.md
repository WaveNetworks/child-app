# Docker & Deployment

> **Note:** The `docker/` scripts (`generate-config.php`, `entrypoint.sh`, `init-databases.sql`) are driven by `CHILD_APP_SLUG` and the `*_DB_NAME` env vars. Some example defaults in `docker-compose.yml` still carry app-specific database names from an earlier bootstrap — override every env var when you scaffold a new child app so nothing tenant-specific leaks in.

---

## Dockerfile

Builds the base image with PHP extensions, Apache modules, and Composer.
Source code is **not** copied into the image — it is bind-mounted at runtime
via docker-compose.yml for live editing.

```dockerfile
FROM php:8.2-apache

# Extensions
RUN docker-php-ext-install pdo pdo_mysql zip gd
RUN pecl install imagick && docker-php-ext-enable imagick

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
```

## docker-compose.yml

### Services

| Service | Image | Ports | Purpose |
|---------|-------|-------|---------|
| app | Built from Dockerfile | 8085:80 | PHP + Apache |
| db | mysql:8.0 | 3306:3306 | Database server |
| mailhog | mailhog/mailhog | 8025:8025 | SMTP testing |

### Environment Variables (app service)

| Variable | Default | Description |
|----------|---------|-------------|
| `ADMIN_DB_NAME` | {CHILD_APP_SLUG}_admin_main | Admin main database |
| `ADMIN_SHARD1_DB_NAME` | {CHILD_APP_SLUG}_admin_shard_1 | Admin shard 1 |
| `ADMIN_SHARD2_DB_NAME` | {CHILD_APP_SLUG}_admin_shard_2 | Admin shard 2 |
| `CHILD_DB_NAME` | {CHILD_APP_SLUG}_main | Child main database |
| `CHILD_SHARD1_DB_NAME` | {CHILD_APP_SLUG}_shard_1 | Child shard 1 |
| `CHILD_SHARD2_DB_NAME` | {CHILD_APP_SLUG}_shard_2 | Child shard 2 |
| `ANTHROPIC_API_KEY` | — | AI API key |
| `FRAMEWORK_PATH` | /var/www/public_html/{CHILD_APP_SLUG}-framework/framework | Local framework path |

### Volumes

| Volume | Mount | Purpose |
|--------|-------|---------|
| Bind: `../admin` | /var/www/public_html/admin | Admin core (live) |
| Bind: `.` | /var/www/public_html/{CHILD_APP_SLUG} | Child app source (live) |
| Bind: `../{CHILD_APP_SLUG}-framework` | /var/www/public_html/{CHILD_APP_SLUG}-framework | Framework (live) |
| Bind: `../demo` | /var/www/public_html/demo | Demo app (live) |
| Named: `files_vol` | /var/www/files | User uploads |
| Named: `db_data` | /var/lib/mysql | Database persistence |

### Health Check

```yaml
db:
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    interval: 5s
    timeout: 3s
    retries: 10
```

App service `depends_on: db: condition: service_healthy`.

## entrypoint.sh

```bash
#!/bin/bash
set -e

mkdir -p /var/www/files/home /var/www/files/branding
chown -R www-data:www-data /var/www/files

# Ensure config dirs are writable
chown -R www-data:www-data \
    /var/www/public_html/admin/config \
    /var/www/public_html/admin/install \
    /var/www/public_html/{CHILD_APP_SLUG}/config

# Install admin composer deps on first run
if [ ! -d /var/www/public_html/admin/vendor ]; then
    cd /var/www/public_html/admin && composer install --no-dev --optimize-autoloader
fi

# Generate configs from env vars (first run only)
if [ ! -f /var/www/public_html/admin/config/config.php ]; then
    php /usr/local/bin/generate-config.php
fi

exec apache2-foreground
```

## docker/init-databases.sql

Creates 6 databases on first MySQL start:
```sql
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_admin_main;
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_admin_shard_1;
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_admin_shard_2;
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_main;
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_shard_1;
CREATE DATABASE IF NOT EXISTS {CHILD_APP_SLUG}_shard_2;
```

## docker/apache.conf

Directory listing is disabled (`-Indexes`). AllowOverride is enabled for `.htaccess` support.

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/public_html
    <Directory /var/www/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Directory /var/www/files>
        Require all denied
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

## Cron Jobs

### Daily (cron/days/)

| File | Purpose |
|------|---------|
| `cohort_snapshot.php` | Capture daily aggregate progress snapshot for cohort timeline |
| `framework_sync.php` | Auto-sync framework data if `auto_sync` enabled |
| `cache_cleanup.php` | Remove expired cache entries from app_cache |

### Weekly (cron/weeks/)

| File | Purpose |
|------|---------|
| `inactive_check.php` | Flag inactive sellers, send notifications |

## Deploy Script (deploy.py)

Python deployment script. Configure via `deploy_config.py` (copy from `deploy_config.sample.py`).

## Development Workflow

```bash
# First time
docker compose up -d --build

# After code changes — just refresh the browser!
# Source is bind-mounted, no rebuild needed.

# Only rebuild when changing Docker infrastructure:
docker compose up -d --build   # Dockerfile, apache.conf, entrypoint.sh, PHP extensions

# View logs
docker compose logs -f app

# Access
# App: http://localhost:8085/{CHILD_APP_SLUG}/   (e.g. /child-app/, /myapp/)
# MailHog: http://localhost:8025
# MySQL: localhost:3306
```

## .htaccess Security

Blocks web access to:
- `include/`, `config/`, `views/`, `snippets/`, `db_migrations/`, `docker/`
- `.env`, `.git`, `.htaccess`
- `composer.json`, `package.json`, `Dockerfile`, `docker-compose.*`, `CLAUDE.md`, `deploy.*`

Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`.
