# 0002: Laravel-First Monorepo

Date: 2026-05-21

## Decision

Wayfindr will begin as a Laravel-first monorepo.

Laravel owns the core product:

- accounts,
- sites,
- agents,
- visitors,
- conversations,
- tickets,
- cobrowse sessions,
- permissions,
- audit logs,
- APIs,
- queues,
- realtime authorization.

SDKs, plugins, and examples live beside the core application so product changes can be developed and tested together.

## Rationale

The platform needs a coherent source of truth. Splitting the server, widget, SDKs, plugin, docs, and examples across multiple repositories before the product shape is proven would increase coordination overhead without adding much value.

A monorepo keeps the early system understandable:

- one issue context,
- one local development path,
- one place for architecture decisions,
- one place to test server/widget compatibility,
- one repository for public project history.

## Consequences

- The repo may contain code with different licenses, so license boundaries must be explicit.
- Empty package directories should not imply stable public APIs.
- Independent repositories can be created later if a package gains its own release cadence.
