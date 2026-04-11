# Docker

Source is **bind-mounted** — edit any file and refresh, no rebuild needed.
`docker compose up -d` starts app (port 8085), db, mailhog (port 8025).
Only rebuild when changing Dockerfile/apache.conf/entrypoint.sh/PHP extensions.
Test credentials: `admin@elevateher.test` / `Admin123!`
