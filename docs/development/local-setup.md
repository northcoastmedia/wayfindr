# Local Setup

Wayfindr is pre-alpha. The current local setup brings up the Laravel server scaffold with Postgres and Redis.

## Requirements

- PHP 8.3 or newer
- Composer 2
- Docker Compose v2

Node.js and npm are only needed when working on Vite-built frontend assets.

## First Run

From the repository root:

```bash
docker compose up -d postgres redis
cd apps/server
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer test
php artisan serve
```

The server runs at [http://localhost:8000](http://localhost:8000). A lightweight health check is available at [http://localhost:8000/health](http://localhost:8000/health).

## Root Shortcuts

The root `Makefile` wraps the common commands:

```bash
make services-up
make server-install
make server-migrate
make server-test
make server-serve
```

## Current Limits

There is no agent workspace, visitor widget, chat flow, cobrowsing flow, or ticketing flow yet.
