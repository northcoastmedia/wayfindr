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

Browser-level Pest tests are intentionally not enabled yet. When Wayfindr needs
full browser coverage for the dashboard or embedded widget, add Pest's browser
plugin as its own slice so the Playwright dependencies and CI expectations are
clear.
