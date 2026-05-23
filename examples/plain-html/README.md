# Plain HTML Example

Minimal script-tag integration for the vanilla Wayfindr widget.

From the repository root:

```bash
cd apps/server
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal from the repository root:

```bash
python3 -m http.server 4173
```

Open `http://127.0.0.1:4173/examples/plain-html/`.

The demo uses the seeded site public key `site_demo_public_key` and talks to
the local Laravel server at `http://127.0.0.1:8000`.

To smoke test live replies instead of the manual refresh path, start Reverb from
`apps/server`, include `pusher-js` on the page, and pass the local Reverb
settings to `Wayfindr.init`.
