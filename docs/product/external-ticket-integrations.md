# External Ticket Integrations

Wayfindr's ticketing system should be useful on its own before it tries to
mirror another platform. GitHub, GitLab, Bitbucket, Jira, and similar tools may
be excellent destinations for engineering follow-up, but they should not become
the source of truth for Wayfindr's support workflow.

The product stance is:

- Wayfindr owns the support ticket, conversation, visitor context, consent
  history, and audit trail.
- External issue trackers receive explicit, scoped follow-up records when an
  agent chooses to send a ticket outward.
- Provider differences are hidden behind small adapter contracts and capability
  flags, not spread through controllers or views.
- Sensitive visitor data is summarized and redacted before leaving Wayfindr
  unless an operator has explicitly configured otherwise.

## Platform Shape

### GitHub

GitHub Issues are repository-scoped and share API surfaces with pull requests.
GitHub is a strong first integration candidate for open source and developer-led
support because repository issues, labels, assignees, milestones, comments, and
cross-links are familiar.

Planning notes:

- Treat GitHub as a repository issue destination, not a replacement ticket
  store.
- Account for the GitHub API caveat that pull requests can appear through issue
  endpoints.
- Keep repository mapping explicit per Wayfindr site or account.
- Start with outbound issue creation and comments before inbound sync.

### GitLab

GitLab issues may live across GitLab.com, self-managed GitLab, GitLab Dedicated,
and project or group contexts. GitLab is attractive for teams that already
connect support follow-up to project planning, milestones, merge requests, and
labels.

Planning notes:

- Store the GitLab base URL so self-hosted installs can target their own GitLab.
- Treat project ID/path and issue IID as provider-specific external identifiers.
- Model GitLab capabilities separately from GitHub because labels, milestones,
  epics, and work items may vary by installation and edition.
- Keep private project access failures non-leaky; a provider `404` can mean
  either "not found" or "not authorized."

### Bitbucket And Atlassian

Bitbucket Cloud has an issue tracker API, but many Atlassian-heavy teams use
Jira as the real ticket or work item system. Bitbucket Cloud, Bitbucket Data
Center, and Jira should therefore be planned as related but separate provider
families.

Planning notes:

- Treat Bitbucket Cloud issues as one adapter and Jira issues as a separate
  future adapter.
- Do not assume every Bitbucket repository has the issue tracker enabled.
- Do not assume Bitbucket Cloud and Bitbucket Data Center share the same API
  shape.
- Keep the first Atlassian integration narrow: create an external record and
  link back to Wayfindr.

## Integration Boundary

Wayfindr should add an integration boundary before it adds any provider-specific
behavior to tickets.

Expected pieces:

- `ticket_external_links`: a local record connecting a Wayfindr ticket to a
  provider object.
- Provider connection records owned by an account, with encrypted credentials
  and visible capability flags.
- Site-to-provider-project mappings so one Wayfindr account can route different
  sites to different repositories or projects.
- Adapter interfaces for creating an external issue, adding comments, fetching
  status, and handling webhooks when supported.
- Audit events for external issue creation, sync attempts, sync failures, and
  manual unlinking.

The first adapter contract should be intentionally small:

- `createIssue(Ticket $ticket, ExternalProject $project): ExternalIssue`
- `addComment(Ticket $ticket, ExternalIssue $externalIssue, string $body): void`
- `capabilities(): ExternalIssueCapabilities`

Inbound sync, status mirroring, assignee mapping, and label mapping can come
later once local ticket lifecycle behavior is stable.

## Data Rules

External issue trackers are not guaranteed to have the same privacy posture as
Wayfindr. Operators may also use public repositories, contractor-visible
projects, or vendor-hosted systems outside their direct control.

Default behavior should be conservative:

- Send a short ticket summary, priority, category, support code, and Wayfindr
  link.
- Do not send raw visitor transcripts by default.
- Do not send cobrowse snapshots, mutation payloads, masked-field metadata, or
  visitor identifiers by default.
- Require an explicit operator setting before exporting transcripts or internal
  notes.
- Record exactly what was sent in the Wayfindr audit trail.

## Suggested Roadmap

1. Add local ticket categories and keep them provider-neutral.
2. Add a provider-neutral external link data model and audit events.
3. Add account-level provider connection placeholders and capability flags.
4. Add GitHub outbound issue creation as the first concrete adapter.
5. Add GitLab outbound issue creation after the adapter boundary proves itself.
6. Evaluate Bitbucket Cloud issues versus Jira based on real operator demand.
7. Add inbound webhooks and sync health once outbound links are dependable.

## Open Questions

- Should external issue creation be account-wide, site-scoped, or both?
- Should ticket categories map to provider labels by default, or only after an
  operator configures explicit mappings?
- Should external sync failures create agent alerts, dashboard warnings, or both?
- Should public repository destinations require an additional warning before
  transcripts or internal notes can be exported?

## Source Notes

- GitHub REST Issues API: <https://docs.github.com/en/rest/issues/issues>
- GitLab Issues API: <https://docs.gitlab.com/api/issues/>
- Bitbucket Cloud Issue Tracker API:
  <https://developer.atlassian.com/cloud/bitbucket/rest/api-group-issue-tracker/>
