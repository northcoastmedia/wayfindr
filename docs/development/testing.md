# Testing

Wayfindr's Laravel server uses Pest 4 for PHP tests.

Run the server suite from `apps/server`:

```bash
composer test
```

The suite currently has three layers:

- `tests/Feature` covers Laravel HTTP, console, database, and product workflow behavior.
- `tests/Unit` covers isolated runtime or domain behavior that does not need the Laravel app.
- `tests/Architecture` keeps lightweight structural rules around application code.

Prefer Pest-style tests for new PHP coverage. Use Laravel feature tests for
public APIs, dashboard flows, commands, authorization, and persistence behavior.
Use architecture tests for durable project rules that should stay true across
many future slices.

## Public-Info Boundary Check

Run the repository boundary guard from the root before opening a pull request:

```bash
make public-info-check
```

The guard scans tracked files for sensitive markers so non-public material stays
out of the public repository. To turn on the committed pre-commit hook template
for this checkout:

```bash
git config core.hooksPath .githooks
```

The guard has its own fixture test:

```bash
make public-info-test
```

Browser-level Pest tests are intentionally not enabled yet. When Wayfindr needs
full browser coverage for the dashboard or embedded widget, add Pest's browser
plugin as its own slice so the Playwright dependencies and CI expectations are
clear.
