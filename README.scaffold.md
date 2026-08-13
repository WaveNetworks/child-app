# <app>

A Wave Networks child application.

Admin core (`../admin/`) provides auth, sessions, shard routing, email,
notifications, theming, branding, error logging and GDPR. This app adds its own
databases, views, actions and features on top.

## Layout

    app/index.php        entry point; ?page= routing
    views/               pages, included by app/index.php
    include/actions/     memberActions (session) and apiActions (AJAX/service)
    include/common/      helpers, auto-included
    db_migrations/       main/ and shard/ SQL, applied on first page load

## Getting started

`docs/spec.md` is the source of truth for what this app should do.

Build features, not documentation: each user-facing screen is a view plus its
actions plus any read API, wired into `app/index.php` routing and the sidebar
nav in `views/template.php`.
