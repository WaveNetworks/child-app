# Overview

## What this is
Child application for the Wave Networks ecosystem. Deployed at `public_html/elevateher/`
as a sibling of `public_html/admin/`. Admin core provides auth, sessions, sharding,
email, notifications, theming, branding. This app adds its own databases, views, actions, theme.

Bootstrap chain: `elevateher/include/common.php` → `admin/include/common.php`

## Path reference
```
auth/*.php          → include: ../include/common_auth.php
app/index.php       → include: ../include/common.php
api/index.php       → include: ../include/common_api.php
views/*.php         → included BY app/index.php (never directly)
include/common.php  → include: ../../admin/include/common.php
```
