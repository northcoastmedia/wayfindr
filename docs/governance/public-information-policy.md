# Public Information Policy

This repository is public. Assume every committed file will be read by users, contributors, competitors, customers, vendors, and people with unusual amounts of free time.

## Public Repository Scope

Commit information that helps people understand, use, self-host, audit, or contribute to Wayfindr:

- product principles,
- user-facing product decisions,
- architecture decisions,
- security and privacy model,
- data inventory, retention posture, and operator responsibility,
- license decisions,
- contribution process,
- self-hosting documentation,
- API and SDK documentation,
- public roadmap items,
- release notes,
- AI feature posture, provider-neutral configuration guidance, and synthetic
  examples that help users understand Wayfindr's behavior.

## Keep Private

Do not commit:

- business strategy,
- pricing experiments,
- revenue targets,
- customer or prospect names,
- sales notes,
- private support incidents,
- customer transcripts, AI prompts, AI outputs, or evaluation datasets that
  contain real customer, visitor, employee, or private business information,
- private infrastructure details,
- cloud cost models,
- vendor credentials,
- AI provider API keys or account identifiers,
- tokens or secrets,
- competitive strategy,
- internal-only launch plans.

## Business-Adjacent Public Decisions

Some business-adjacent decisions may be public when they affect users or contributors. These should be written in product terms.

Acceptable:

- "Wayfindr CE is AGPL-licensed."
- "SDKs are permissively licensed to make embedding straightforward."
- "Wayfindr Cloud may include managed-hosting features not present in CE."
- "CE is community supported."

Do not include:

- private margin assumptions,
- specific customer willingness to pay,
- sales pipeline information,
- unreleased pricing strategy,
- private operational costs.

## Local Notes

Private business notes should not live inside this repository directory. `.gitignore` includes local safety patterns, but ignore rules are a backstop, not a privacy strategy. Keep sensitive working notes outside the repo.
