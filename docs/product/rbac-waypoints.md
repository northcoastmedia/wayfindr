# RBAC Waypoints

Wayfindr should use role-based access control for account authority, while keeping site access as the support scope layer. This keeps the platform familiar for serious SaaS/PaaS operators without turning every support queue into a bespoke permissions maze.

## North Star

Every protected action should answer four questions in this order:

1. Is the person authenticated?
2. Is the person a member of the account?
3. Does the person's role allow this action?
4. If the action touches support data, does the person have access to the relevant site?

Roles answer "what can this person do?" Site access answers "where can this person do support work?"

## Vocabulary

- `Account`: tenant boundary for sites, agents, tickets, conversations, audit events, privacy settings, and future billing.
- `User`: authenticated person.
- `Membership`: the user's relationship to an account. Wayfindr starts with `users.account_role` while users belong to one account; a dedicated membership model can replace it when multi-account users become necessary.
- `Role`: named bundle of account authority.
- `Permission`: specific action granted by a role.
- `Site access`: support scope for conversations, tickets, alerts, realtime channels, and cobrowse sessions.

## Starting Roles

The first RBAC implementation should start with three roles:

- `owner`: can manage account settings, agents, roles, sites, site access, privacy settings, and support operations for sites they can access.
- `admin`: can manage agents, sites, site access, privacy settings, and support operations for sites they can access, but cannot transfer ownership, manage billing, or grant ownership.
- `agent`: can work assigned support queues, reply to conversations, create and update tickets, request cobrowse consent, and manage their own alerts.

Later roles can be added when the product proves the need:

- `billing`: can manage billing and plan details without support queue access.
- `viewer`: can view reporting or history without participating in conversations.

## Permission Inventory

These are the first permissions Wayfindr should model explicitly:

| Permission | Owner | Admin | Agent | Site access required |
| --- | --- | --- | --- | --- |
| `manage_account` | yes | no | no | no |
| `manage_billing` | yes | no | no | no |
| `manage_agents` | yes | yes | no | no |
| `manage_roles` | yes | no | no | no |
| `manage_sites` | yes | yes | no | no |
| `manage_site_access` | yes | yes | no | no |
| `manage_privacy_settings` | yes | yes | no | yes |
| `view_conversations` | yes | yes | yes | yes |
| `reply_to_conversations` | yes | yes | yes | yes |
| `request_cobrowse` | yes | yes | yes | yes |
| `manage_tickets` | yes | yes | yes | yes |
| `assign_tickets` | yes | yes | yes | yes |
| `view_alerts` | yes | yes | yes | yes |
| `manage_retention` | yes | yes | no | no |

This inventory should be tested before implementation, then refined when real product work exposes a missing action.

## Site Access Boundary

Site access remains separate from RBAC. It already controls which agents can support which sites, and it should continue to protect:

- dashboard support queues,
- conversation detail pages,
- ticket detail pages,
- assignment options,
- alerts,
- realtime/broadcast channels,
- cobrowse requests and updates,
- site privacy settings.

Owner and admin roles may eventually need elevated views across all sites, but that must be explicit. No future role should accidentally bypass site access because a controller checked only `account_id`.

Until that explicit decision is made, owner/admin support queue access still follows site access. A management view may list sites and assignment metadata for admins, but it must not expose conversation bodies, ticket details, alerts, cobrowse state, or visitor page data unless the admin also has support access to that site.

The first `manage_site_access` implementation requires owner/admin authority plus support access to the site, matching the current site settings screen. A later metadata-only account administration surface may allow admins to assign agents to sites they do not personally support, but that should be a separate product decision with tests for cross-account denial, cross-site denial, self-assignment behavior, and attempts to assign agents outside the account.

Site privacy settings require owner/admin authority plus site access. Plain agents can still view install and public masking context for sites they support, but they cannot edit privacy configuration.

## Laravel Authorization Shape

RBAC should be implemented through Laravel policies and gates. Controllers should ask authorization questions instead of composing ad hoc account, role, and site checks inline.

Examples:

```php
$this->authorize('manageSiteAccess', $site);
$this->authorize('replyTo', $conversation);
$this->authorize('assign', $ticket);
```

Policy methods should compose small checks:

- account membership,
- role permission,
- site access when support data is involved.

## Audit Guardrails

Any action that changes who can see or affect visitor/customer data should create an audit event.

Audit-worthy RBAC actions include:

- role changes,
- owner transfer,
- agent invitations,
- agent removal,
- site access changes,
- privacy setting changes,
- retention setting changes,
- cobrowse consent lifecycle events,
- ticket assignment changes.

## Bootstrap Rules

Self-hosted setup should remain hard to lock yourself out of:

- the first bootstrap user becomes `owner`,
- the first bootstrap site is assigned to that user,
- sites with no explicit support agents remain account-wide until site access is configured,
- once a site has explicit support agents, support queues become site-scoped.

## Sanity Checks For Every RBAC Slice

Every RBAC implementation slice should include tests for:

- cross-account denial,
- wrong-site denial,
- role denial,
- owner/admin/agent differences,
- stale assignment behavior,
- stale alert visibility,
- role escalation denial,
- last-owner protection,
- background notification routing,
- realtime channel authorization,
- audit event creation for permission changes,
- bootstrap lockout prevention.

## Implementation Waypoints

1. Document the vocabulary, permissions, and role matrix.
2. Introduce `users.account_role` as the starter role field.
3. Add role helpers without changing behavior.
4. Move one support surface at a time behind policies. Site settings now use `SitePolicy` for `view`, `updatePrivacy`, and `manageAccess`; agent conversation detail, reply, status, assignment, ticket creation, cobrowse request, and realtime channel access now use `ConversationPolicy`; ticket detail, notes, edits, status changes, and assignment now use `TicketPolicy`.
5. Tighten site privacy settings behind owner/admin authority plus site access. Site privacy settings now follow this rule.
6. Add role management UI only after policies exist.
7. Add audit events for role and site-access changes.
8. Add owner/admin elevated behavior only when the product decision is explicit.

## Role Management Guardrails

The first implementation should keep role management owner-only. Before admins can ever manage roles, Wayfindr needs explicit tests and product rules for:

- preventing self-promotion,
- preventing owner transfer without owner approval,
- preventing demotion or removal of the last owner,
- limiting which roles an admin can grant,
- recording every role change as an audit event.
