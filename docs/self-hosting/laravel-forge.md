# Laravel Forge Deployment

This is the first-class staging/demo deployment path for Wayfindr. Forge gives
us a plain Laravel runtime, managed deploy hooks, queue workers, scheduled
jobs, TLS, and health checks without introducing our own platform layer yet.

Forge is recommended, not required. Wayfindr can run on any infrastructure that
can provide the same Laravel, Postgres, Redis, queue, scheduler, and realtime
services. The Forge path gets the most complete docs first because this project
is Laravel-first and Forge is a strong fit for Laravel applications.

No partnership, sponsorship, or hosting requirement is implied.

Wayfindr is still pre-alpha. Use this environment for product validation and
integration smoke tests, not for real customer data.

## Source Control Access

For anonymous-user testing, deploy from your own fork of Wayfindr. A random
self-hoster cannot add deploy keys to the upstream Wayfindr repository, and
they should not need to.

Recommended flow:

1. Fork Wayfindr into your GitHub account or organization.
2. In Forge, choose `Custom Git` as the source control provider.
3. Turn on `Generate a site deploy key for your source control provider`.
4. Add the generated key to your fork's GitHub `Settings > Deploy keys`.
5. Leave `Allow write access` unchecked.
6. Use the fork's SSH repository URL in Forge.

Example repository URL:

```text
git@github.com:your-org/wayfindr.git
```

If GitHub says `Key is already in use`, Forge is probably showing a server-level
SSH key that has already been attached to another repository. GitHub does not
allow the same public key to be reused as a deploy key across repositories. Use
a site deploy key instead so the key is unique to this Wayfindr site.

Using Forge's connected GitHub provider is also fine for teams that prefer
OAuth/App-based access, but the fork plus site deploy key path is the cleanest
repo-scoped install flow to document for self-hosters.

## Forge Site Shape

- Project type: Laravel.
- Repository: your fork, for example `your-org/wayfindr`.
- Initial deploy branch: `feat/forge-deployment-readiness` until the stack is
  merged to `main`; use `main` for stable releases once available.
- PHP: 8.3 or newer.
- Database: Postgres.
- Cache/queue: Redis.
- Root directory: `/`.
- Web directory: `/apps/server/public`.
- Health check URL: `/up`.

For a new Forge site, leave zero-downtime deployments enabled. Forge enables
this by default for new sites, and it cannot be added to an existing site
later. Add `apps/server/storage` as a shared path so logs and uploaded files
survive release swaps. If Forge pre-populates a `storage -> storage` shared
path, change it to `apps/server/storage -> apps/server/storage` for Wayfindr's
monorepo layout. Forge automatically shares `.env` for zero-downtime sites.

Turn off Forge's creation-time `Install Composer dependencies` option. Wayfindr
is a monorepo, and Forge runs that automatic Composer install from the repository
root. Our `composer.json` lives in `apps/server`, so the install belongs in the
deploy script instead. Also leave the creation-time frontend build disabled; the
deploy script will run it only after the Laravel app has a lockfile to build
from.

## Environment

Manage these values in Forge's site environment editor, not in Git:

```dotenv
APP_NAME=Wayfindr
APP_ENV=staging
APP_KEY=base64:replace-with-generated-key
APP_DEBUG=false
APP_URL=https://replace-with-forge-site-host

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=replace-with-forge-db-host
DB_PORT=5432
DB_DATABASE=replace-with-forge-db-name
DB_USERNAME=replace-with-forge-db-user
DB_PASSWORD=replace-with-forge-db-password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.test
MAIL_FROM_NAME="${APP_NAME}"
```

Generate the `APP_KEY` on the server with:

```bash
cd ~/wayfindr.on-forge.com/current/apps/server
php artisan key:generate --show
```

Forge's zero-downtime `current` directory points to the monorepo root, not the
Laravel application root. Run Artisan commands from `apps/server`:

```bash
cd ~/wayfindr.on-forge.com/current/apps/server
php artisan about
```

## Deploy Script

Use [zero-downtime-deploy.forge](../../deploy/forge/zero-downtime-deploy.forge)
as the Forge deploy script for new sites. It assumes Forge's zero-downtime
release macros are available and that commands run from the monorepo root. The
macro lines are Forge-specific and are not meant to run in a local shell. After
the release is activated, the script restarts Laravel queues from the active
`current` release with `php artisan queue:restart`.

If the site was created with `Install Composer dependencies` enabled and Forge
reports `Composer could not find a composer.json file`, the site can still be
used. Replace the generated deploy script with Wayfindr's script, set the
environment variables, and run a fresh deploy.

If deployment reaches `view:cache` and fails with `View path not found`, verify
the shared storage path is `apps/server/storage -> apps/server/storage` and that
the deploy script includes Wayfindr's runtime-directory preparation step. The
failure usually means Forge linked an empty shared storage directory before the
Laravel storage subdirectories existed.

If zero-downtime deployments were disabled when the site was created, use
[standard-deploy.sh](../../deploy/forge/standard-deploy.sh) instead.

Both scripts:

- install production Composer dependencies from `apps/server`,
- skip the frontend build until a `package-lock.json` exists,
- run migrations with `--force`,
- cache config, routes, and views,
- restart queues after deploy.

## Queues And Scheduler

Create one Forge queue worker for the site:

```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=90
```

Use the Laravel Scheduler toggle in Forge's Application panel. Forge will
configure it to run once per minute using the site's selected PHP version.

There are no critical scheduled tasks yet, but enabling the scheduler now keeps
the staging runtime close to the expected production shape.

## First Deploy Checklist

1. Provision a Forge server with PHP 8.3+, Postgres, Redis, and Nginx.
2. Fork Wayfindr to your GitHub account or organization.
3. Create the Forge site using `Custom Git`.
4. Enable `Generate a site deploy key for your source control provider`.
5. Add the generated key to the fork as a read-only GitHub deploy key.
6. Use the fork's SSH repository URL and the target deploy branch.
7. Set root directory to `/` and web directory to `/apps/server/public`.
8. Turn off Forge's creation-time Composer install and frontend build options.
9. Keep zero-downtime deployments enabled.
10. Add the environment values above in Forge.
11. Replace the generated deploy script with Wayfindr's deploy script.
12. Run the first deploy.
13. Enable TLS before testing the widget from another origin.
14. Enable the deployment health check against `/up`.
15. Add the queue worker and scheduler.
16. Log in with the seeded demo agent only if the seeder has been run:
   `agent@example.com` / `password`.

## Smoke Test

From a local machine with `curl` and PHP available:

```bash
WAYFINDR_BASE_URL=https://replace-with-forge-site-host \
WAYFINDR_SITE_PUBLIC_KEY=site_demo_public_key \
./scripts/smoke/widget-intake.sh
```

Then sign in to the Forge site as the demo agent and confirm the smoke
conversation appears in the agent inbox.

## Rollback

For zero-downtime deployments, use Forge's deployment history to reactivate the
previous release. If a migration shipped with the failed deploy, inspect it
before rolling back the database. Do not run destructive rollbacks against a
shared staging database unless the data can be thrown away.

For standard deployments, redeploy the previous known-good commit or branch and
run:

```bash
cd apps/server
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## References

- [Forge deployments](https://forge.laravel.com/docs/sites/deployments)
- [Forge environment variables](https://forge.laravel.com/docs/sites/environment-variables)
- [Forge queues](https://forge.laravel.com/docs/sites/queues)
- [Forge Laravel application panel](https://forge.laravel.com/docs/sites/laravel)
- [Forge source control](https://forge.laravel.com/docs/source-control)
- [Forge SSH and deploy keys](https://forge.laravel.com/docs/ssh)
