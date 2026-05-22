# Self-Hosting Install

Wayfindr is pre-alpha. The current Docker Compose file is for local
development only, but the first staging/demo deployment target is Laravel
Forge.

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
