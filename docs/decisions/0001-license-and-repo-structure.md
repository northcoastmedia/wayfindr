# 0001: License And Repository Structure

Date: 2026-05-21

## Decision

Wayfindr will start as a public monorepo with a mixed license structure:

- Core Laravel product: `AGPL-3.0-or-later`
- Browser widget SDK: MIT
- React widget package: MIT
- Laravel host-app SDK: MIT
- WordPress plugin: GPL-compatible
- Docker/self-hosting templates: MIT
- Documentation: permissive license to be finalized
- Name, logo, and marks: trademark-protected

## Rationale

Wayfindr is a customer-facing support platform, not merely a library. A permissive license for the full product would make it easy for closed hosted services to take the code, resell it, and avoid contributing changes back.

AGPL is the intended license for the core product because it addresses network use of modified server software while remaining an OSI-approved open source license.

SDKs and integration packages need a different posture. They are meant to be embedded into other applications and websites. MIT is the initial default for these packages because permissive licensing reduces adoption friction and avoids accidentally making host applications uncomfortable with the core product license.

## Consequences

- The root license applies to the core product by default.
- Subdirectories with different licensing must include their own `LICENSE` file.
- Package metadata must match the nearest license.
- Public documentation should not describe a package as dual-licensed unless the package license file does the same.
- Contributors need clear guidance before broad external contribution begins.
- Cloud-only or commercial code must remain clearly separated from Community Edition code.
