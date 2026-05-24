# Self-Hosting Install

Wayfindr is pre-alpha. The current Docker Compose file is for local
development only, but the first staging/demo deployment target is Laravel
Forge.

Forge is the first-class documented path because Wayfindr is Laravel-first and
Forge handles the normal Laravel runtime pieces well: PHP, Nginx, Postgres,
Redis, queues, scheduler, TLS, health checks, and deploy hooks. There is no
hosting requirement or implied partnership here; Forge is simply the clearest
path we are choosing to maintain first.

Self-hosters control their own visitor data, logs, backups, retention windows,
privacy notices, and deletion/export workflows. Review
[data-responsibility.md](../privacy/data-responsibility.md) before using
Wayfindr with real visitors.

Use [laravel-forge.md](laravel-forge.md) for the current Forge deployment
checklist, deploy script templates, environment values, queue worker, scheduler,
and smoke test.

The intended self-hosting baseline is still Docker Compose with:

- Laravel web process,
- queue worker,
- scheduler,
- realtime process,
- Postgres,
- Redis.

HTTPS and secure WebSocket configuration will be required for production.
