# Accounts, Agents, Site Access, and Roles

Wayfindr should let one support organization run support across many sites without assuming every agent works every property. This document separates the concepts so the product can grow without turning early access checks into a permanent permissions model.

## Current Model

### Account

An account is the tenant boundary. Sites, agents, tickets, conversations, audit events, and privacy settings belong inside an account.

### Agent

An agent is a Laravel user attached to one account. Agents can sign in, view the agent dashboard, reply to conversations, request cobrowse consent, and work tickets when they have access to the relevant site.

### Site access

Site access controls which agents support which sites. Conversations, tickets, alerts, ticket assignment options, and realtime conversation channels should respect site access.

If a site has no assigned support agents yet, Wayfindr treats it as account-wide so first-run installs and existing self-hosted setups do not lock themselves out. Once one or more support agents are assigned to a site, only those agents should see and work that site's support queue.

## Future Account Roles

Account roles are about authority, not queue membership. They are not implemented yet.

The likely first roles are:

- `owner`: can manage account-level settings, billing or hosted-plan details, agents, roles, sites, and all support queues.
- `admin`: can manage agents, sites, privacy settings, and support operations, but may not own billing or account transfer controls.
- `agent`: can work assigned support queues, reply to visitors, create and update tickets, request cobrowse consent, and manage their own alert workflow.

Possible later roles:

- `billing`: can manage billing and plan details without support queue access.
- `viewer`: can view reports or support history without participating in conversations.

## Guardrails

- Site access should land before broad role management.
- Role checks should build on explicit account membership and site access, not replace them.
- Ticket assignment should only target agents who can support the ticket's site.
- Alerts should notify the smallest useful group: assigned agent first, otherwise agents assigned to the site, otherwise the account-wide fallback.
- Cobrowse access should require both account membership and site access.
- Self-hosted operators remain responsible for mapping roles to their local policies, employment rules, and data obligations.

## Open Questions

- Should newly created agents be added to all current sites by default, no sites by default, or selected sites only?
- Should site access management be owner/admin-only from the first role slice?
- Should WordPress and future integrations expose site-agent assignment hints during install?
- Should account roles be stored as a simple enum on account membership, or should Wayfindr introduce a dedicated membership model before hosted multi-tenant plans?
