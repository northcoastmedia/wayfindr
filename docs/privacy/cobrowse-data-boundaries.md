# Cobrowse Data Boundaries

Wayfindr cobrowse is shared page state, not a video feed of a visitor browser.
The browser widget should send only the state needed for an agent to understand
where the visitor is stuck, and sensitive values should be masked before
anything leaves the visitor browser.

## Consent Boundary

Cobrowse requires an active agent request and explicit visitor consent. A
visitor granting cobrowse consent means Wayfindr may send sanitized page state
for that session. It does not mean the visitor has agreed to disclose passwords,
payment details, tokens, or unrelated personal data.

## Before Consent

Before cobrowse consent, the widget may:

- bootstrap the site and anonymous visitor,
- create or update a conversation,
- send visitor messages,
- fetch the current cobrowse request status.

It must not send page snapshots, mutation batches, or cobrowse telemetry until
consent is granted for an active cobrowse session.

## After Consent

After consent, the widget may send:

- page URL and title,
- viewport, scroll, focus, and visibility state,
- lightweight connection telemetry,
- a sanitized DOM snapshot,
- bounded mutation batches.

Those payloads should remain compact, bounded, and recoverable. They should not
be treated as a permanent recording of the visitor's browser.

## Masking Boundary

The widget masks explicit selectors and inferred sensitive elements before
cobrowse data leaves the page. Current built-in explicit selectors include:

- `input[type="password"]`,
- `input[type="hidden"]`,
- `[data-wayfindr-mask]`,
- `[data-wayfindr-private]`,
- `[data-secret]`.

The widget also infers common sensitive fields from attributes such as `id`,
`name`, `autocomplete`, `aria-label`, `placeholder`, `data-field`,
`data-wayfindr-field`, `data-testid`, `data-test`, and `data-cy`. Early terms
include password, token, API key, SSN, tax ID, credit card, CVV, routing number,
account number, username, email, phone, address, and birthdate language.

Host applications can use `data-wayfindr-mask` or `data-wayfindr-private` for
known sensitive areas. They can use `data-wayfindr-allow` only for deliberate
false positives where the surrounding content is safe to share.

## AI Boundary

AI can eventually help suggest masking selectors or flag risky page structures,
but it must work from sanitized structure, labels, and selectors. Raw visitor
values should not be sent to an AI provider for masking decisions.

## Agent Experience

Agents should see whether cobrowse is unavailable, requested, granted, revoked,
or ended. They should also see when no snapshot or telemetry has arrived yet.
The visitor experience should stay simple: allow, decline, stop, and clear copy
about sensitive fields being masked.
