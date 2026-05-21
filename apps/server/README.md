# Wayfindr Server

This is the Laravel core application for Wayfindr.

The server owns accounts, sites, agents, visitors, conversations, tickets, cobrowse sessions, permissions, audit logs, APIs, queues, and realtime authorization.

## Local Commands

From this directory:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer test
```

Use the repository root `docker-compose.yml` for local Postgres and Redis services.

License: `AGPL-3.0-or-later`.
