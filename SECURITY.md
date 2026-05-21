# Security Policy

Wayfindr handles support conversations, visitor context, and cobrowsing sessions. Security and privacy issues should be treated as product-critical.

## Reporting Vulnerabilities

Until a dedicated security contact is published, please do not open public issues with vulnerability details.

Use a private contact path with the project maintainer when one is available. If no private path is available, open a public issue requesting a security contact, but do not include exploit details, affected URLs, credentials, logs, or reproduction steps. Public reporting instructions will be updated before the first public release.

## Security Principles

- Mask sensitive cobrowsing content before it leaves the visitor browser.
- Require explicit consent for cobrowsing and separate consent for remote control.
- Treat widget site keys as public identifiers, not secrets.
- Use short-lived signed tokens for privileged viewer/session access.
- Keep secrets out of the repository.
- Avoid logging raw cobrowsing payloads or sensitive chat content unnecessarily.
