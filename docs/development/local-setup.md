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
php artisan wayfindr:bootstrap \
  --account="Demo Support Co" \
  --name="Demo Agent" \
  --email="agent@example.com" \
  --password="password" \
  --site="Demo Site" \
  --domain="demo.test" \
  --site-public-key="site_demo_public_key"
composer test
php artisan serve
```

`composer test` runs the Laravel server's Pest 4 suite, including feature,
unit, and architecture tests.

See [testing.md](testing.md) for the current testing posture.

The server runs at [http://localhost:8000](http://localhost:8000). A lightweight health check is available at [http://localhost:8000/up](http://localhost:8000/up).

The local bootstrap credentials above are `agent@example.com` / `password`, and
the widget public key is `site_demo_public_key`.

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

The current prototype supports agent login, public widget intake, visitor
conversation creation, agent replies from the conversation inbox, and
visitor-visible message retrieval through the public widget API. Realtime
updates, cobrowsing, ticket workflows, and production hardening are still ahead.
