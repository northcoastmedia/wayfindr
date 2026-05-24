# Data Inventory

This inventory describes the intended product data surface for the current
pre-alpha Wayfindr application. It is a planning and operator-awareness
document, not a complete compliance register.

## Current Records

| Record | Examples | Current storage | Notes |
| --- | --- | --- | --- |
| Accounts | Support team name | `accounts` | Tenant boundary for sites, agents, tickets, and conversations. |
| Agents | Name, email, hashed password, account ID | `users` | Agents are authenticated Laravel users. |
| Sites | Name, domain, public widget key, settings | `sites` | Site settings can include mask selectors. Internal settings should not be exposed to the widget unless explicitly safe. |
| Visitors | Anonymous ID, optional external ID, optional name/email, last seen time, metadata | `visitors` | Widget traffic is scoped by site public key and signed visitor token. |
| Conversations | Subject, status, support code, visitor/site links, page URL metadata | `conversations` | Conversation subjects and page URLs can contain personal data depending on the host site. |
| Messages | Visitor and agent message bodies, sender references, timestamps, metadata | `conversation_messages` | Message bodies are user-supplied support data. |
| Tickets | Subject, status, priority, requester, assignee, conversation link, metadata | `tickets` | Tickets are durable support records and may outlive the original chat. |
| Cobrowse sessions | Consent status, requested/consented/ended timestamps, telemetry, page state, sanitized snapshot, mutation buffer | `cobrowse_sessions` | Cobrowse data should be masked in the browser before transmission. |
| Audit events | Actor, subject, event type, metadata, timestamps | `audit_events` | Intended for accountability and important lifecycle events. |
| Sessions, cache, queues | Laravel runtime data | Laravel cache/session/job tables or configured drivers | Runtime stores may include identifiers or serialized job payloads. |
| Logs | Errors, deployment diagnostics, request/runtime details | Application and infrastructure logs | Operators should avoid logging secrets and should rotate logs. |
| Backups | Database and file snapshots | Operator infrastructure | Backups can retain deleted application data unless the operator has a backup lifecycle policy. |

## Data Wayfindr Should Avoid

Wayfindr should not intentionally collect or store:

- visitor passwords,
- raw payment card numbers or CVV values,
- raw API keys, access tokens, or secrets from host pages,
- full video or pixel streams of a visitor browser,
- unbounded page snapshots or mutation streams,
- AI training datasets made from customer conversations without explicit,
  separate controls.

## Retention Posture

The current application does not yet ship automatic data retention controls.
Until those controls exist, operators should assume database records, logs, and
backups persist according to their infrastructure defaults.

Future retention controls should let operators decide how long each data class
is kept and should show the data responsibility reminder before saving unusually
long or indefinite retention windows.
