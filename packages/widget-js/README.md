# Wayfindr Widget JS

Classic script-compatible browser widget SDK for Wayfindr.

The initial package exposes a small global API and is intentionally friendly to
plain HTML sites:

```html
<script src="https://your-wayfindr-host.example/widget.js"></script>
<script>
  Wayfindr.init({
    apiBaseUrl: 'https://your-wayfindr-host.example',
    sitePublicKey: 'site_demo_public_key',
  });
</script>
```

For local development inside this monorepo, use
`../../packages/widget-js/src/wayfindr-widget.js` from the plain HTML example.

The widget currently supports the first visitor loop:

- bootstrap the site config, anonymous visitor, and signed visitor token,
- start a conversation,
- send visitor messages,
- render the visitor-visible conversation message timeline,
- manually refresh for agent replies.

```js
const client = Wayfindr.createClient({
  apiBaseUrl: 'https://your-wayfindr-host.example',
  sitePublicKey: 'site_demo_public_key',
});

const result = await client.sendFirstMessage('Can you help me?', {
  pageUrl: window.location.href,
});

const timeline = await client.fetchMessages(result.conversation.support_code);
```

`sendFirstMessage` bootstraps the visitor session automatically when needed.
Lower-level calls such as `startConversation`, `sendMessage`, and
`fetchMessages` expect the visitor to have been bootstrapped first.

## Development

```bash
npm test
```

The script attaches `window.Wayfindr` for classic script tags and also exports
the same API through CommonJS so the package can be tested without a browser
build step.

License: MIT.
