# Wayfindr Widget JS

Classic script-compatible browser widget SDK for Wayfindr.

The initial package exposes a small global API and is intentionally friendly to
plain HTML sites:

```html
<script src="../../packages/widget-js/src/wayfindr-widget.js"></script>
<script>
  Wayfindr.init({
    apiBaseUrl: 'http://127.0.0.1:8000',
    sitePublicKey: 'site_demo_public_key',
  });
</script>
```

The widget currently supports the first visitor loop:

- bootstrap the site config and anonymous visitor,
- start a conversation,
- send visitor messages,
- fetch the visitor-visible conversation message timeline.

```js
const client = Wayfindr.createClient({
  apiBaseUrl: 'http://127.0.0.1:8000',
  sitePublicKey: 'site_demo_public_key',
});

const timeline = await client.fetchMessages('WF-EXAMPLE');
```

## Development

```bash
npm test
```

The script attaches `window.Wayfindr` for classic script tags and also exports
the same API through CommonJS so the package can be tested without a browser
build step.

License: MIT.
