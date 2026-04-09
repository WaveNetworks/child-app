# child-app -- Docker

## Services

| Service | Image | Ports | Purpose |
|---------|-------|-------|---------|
| app | Built from Dockerfile | 8085:80 | PHP + Apache |
| db | mysql:8.0 | 3306:3306 | Database server |
| mailhog | mailhog/mailhog | 8025:8025 | SMTP testing |

## Build and Rebuild

```bash
# First time
docker compose up -d --build

# After code changes -- just refresh the browser!
# Source is bind-mounted, no rebuild needed.

# Only rebuild when changing Docker infrastructure:
docker compose up -d --build   # Dockerfile, apache.conf, entrypoint.sh, PHP extensions

# View logs
docker compose logs -f app
```

**Access:**
- App: http://localhost:8085/elevateher/
- MailHog: http://localhost:8025
- MySQL: localhost:3306

## Database Naming

Six databases are created on first MySQL start via `docker/init-databases.sql`:

```sql
CREATE DATABASE IF NOT EXISTS elevateher_admin_main;
CREATE DATABASE IF NOT EXISTS elevateher_admin_shard_1;
CREATE DATABASE IF NOT EXISTS elevateher_admin_shard_2;
CREATE DATABASE IF NOT EXISTS elevateher_main;
CREATE DATABASE IF NOT EXISTS elevateher_shard_1;
CREATE DATABASE IF NOT EXISTS elevateher_shard_2;
```

## Config Auto-Generation

The entrypoint script generates config files from environment variables on first run:

1. Creates `/var/www/files/home` and `/var/www/files/branding` directories
2. Sets ownership on config and install directories
3. Installs admin Composer dependencies if `vendor/` is missing
4. Generates `admin/config/config.php` if absent (via `generate-config.php`)
5. Generates `elevateher/config/config.php` if absent (via `generate-elevateher-config.php`)

Once generated, config files persist across container restarts (bind-mounted source directory).

## Environment Variables (app service)

| Variable | Default | Description |
|----------|---------|-------------|
| `ADMIN_DB_NAME` | elevateher_admin_main | Admin main database |
| `ADMIN_SHARD1_DB_NAME` | elevateher_admin_shard_1 | Admin shard 1 |
| `ADMIN_SHARD2_DB_NAME` | elevateher_admin_shard_2 | Admin shard 2 |
| `CHILD_DB_NAME` | elevateher_main | Child main database |
| `CHILD_SHARD1_DB_NAME` | elevateher_shard_1 | Child shard 1 |
| `CHILD_SHARD2_DB_NAME` | elevateher_shard_2 | Child shard 2 |
| `ANTHROPIC_API_KEY` | -- | AI API key |
| `FRAMEWORK_PATH` | /var/www/public_html/dswa-framework/framework | Local framework path |

## Volumes

| Volume | Mount | Purpose |
|--------|-------|---------|
| Bind: `../admin` | /var/www/public_html/admin | Admin core (live) |
| Bind: `.` | /var/www/public_html/elevateher | ElevateHER child app (live) |
| Bind: `../dswa-framework` | /var/www/public_html/dswa-framework | Framework (live) |
| Bind: `../DSWADemo` | /var/www/public_html/DSWADemo | Demo app (live) |
| Named: `files_vol` | /var/www/files | User uploads |
| Named: `db_data` | /var/lib/mysql | Database persistence |

## Health Check

```yaml
db:
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    interval: 5s
    timeout: 3s
    retries: 10
```

App service `depends_on: db: condition: service_healthy`.

## Dockerfile

Builds the base image with PHP extensions, Apache modules, and Composer. Source code is **not** copied into the image -- it is bind-mounted at runtime via docker-compose.yml for live editing.

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

## Apache Configuration

Directory listing is disabled (`-Indexes`). AllowOverride is enabled for `.htaccess` support. The `/var/www/files` directory is denied from web access.

## .htaccess Security

Blocks web access to:
- `include/`, `config/`, `views/`, `snippets/`, `db_migrations/`, `docker/`
- `.env`, `.git`, `.htaccess`
- `composer.json`, `package.json`, `Dockerfile`, `docker-compose.*`, `CLAUDE.md`, `deploy.*`

Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`.

## Deploy Script

`deploy.py` -- Python deployment script. Configure via `deploy_config.py` (copy from `deploy_config.sample.py`).
