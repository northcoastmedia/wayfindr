# Wayfindr

Wayfindr is an open source, self-hostable customer support platform for live chat, cobrowsing, and ticketing.

The project is intentionally early. The first goal is to prove a focused support loop:

- install a small widget on a site,
- identify anonymous or authenticated visitors,
- chat with a support agent,
- request consent-based cobrowsing,
- create a durable ticket from the support session.

Wayfindr is a Laravel-first monorepo. Laravel owns the core product, while SDKs and integrations make it portable across WordPress, Laravel, Next.js, React, and plain JavaScript sites.

## Repository Layout

```text
apps/
  server/              Laravel core application
packages/
  widget-js/           Browser widget SDK
  react-widget/        React integration package
  laravel-sdk/         Laravel host-app integration package
plugins/
  wordpress/           WordPress integration plugin
examples/
  plain-html/          Minimal script-tag example
  nextjs/              Next.js example app
  laravel/             Laravel host-app example
docs/
  architecture/        Technical architecture notes
  decisions/           Public product and engineering decisions
  development/         Contributor setup and workflows
  governance/          Public project governance
  product/             Product principles, editions, roadmap
  self-hosting/        Installation and operations docs
docker/                Local and self-hosting templates
```

## Licensing

Wayfindr uses a deliberately mixed license structure:

- Core product/server code is licensed under `AGPL-3.0-or-later`.
- Embeddable SDKs and host-app integrations use permissive licenses, with MIT as the initial package default.
- WordPress plugin code is intended to use a GPL-compatible license.
- Wayfindr names, logos, and marks are not covered by the code license.

See [0001-license-and-repo-structure.md](docs/decisions/0001-license-and-repo-structure.md) for the current licensing rationale.

## Public Documentation Boundary

This is a public open source repository. Product, architecture, security, license, and contribution decisions should be documented here when they affect users or contributors.

Business strategy, pricing strategy, customer/prospect information, private infrastructure, revenue planning, and commercially sensitive notes must stay outside this repository.

See [public-information-policy.md](docs/governance/public-information-policy.md).

## Status

Pre-alpha. The Laravel server scaffold exists, but no usable support workflow exists yet.
