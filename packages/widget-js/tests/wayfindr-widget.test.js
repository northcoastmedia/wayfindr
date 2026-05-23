const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');

const Wayfindr = require('../src/wayfindr-widget.js');

test('attaches the public API to window for classic script tags', () => {
  const source = fs.readFileSync(path.join(__dirname, '../src/wayfindr-widget.js'), 'utf8');
  const sandbox = { window: {} };

  vm.runInNewContext(source, sandbox);

  assert.equal(typeof sandbox.window.Wayfindr.init, 'function');
  assert.equal(typeof sandbox.window.Wayfindr.createClient, 'function');
});

test('resolves a stable anonymous id from storage when one is not supplied', () => {
  const values = new Map();
  const storage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
  };

  const first = Wayfindr.resolveAnonymousId({
    sitePublicKey: 'site_public_docs',
    storage,
  });
  const second = Wayfindr.resolveAnonymousId({
    sitePublicKey: 'site_public_docs',
    storage,
  });

  assert.equal(first, second);
  assert.match(first, /^anon_/);
});

test('bootstraps the widget against the public intake API', async () => {
  const calls = [];
  const client = Wayfindr.createClient({
    apiBaseUrl: 'http://127.0.0.1:8000/',
    sitePublicKey: 'site_public_docs',
    anonymousId: 'anon-browser-123',
    fetch: async (url, options) => {
      calls.push({ url, options });

      return jsonResponse(201, {
        data: {
          site: {
            public_key: 'site_public_docs',
            settings: {
              mask_selectors: ['input[type="password"]'],
            },
          },
          visitor: {
            anonymous_id: 'anon-browser-123',
            token: 'visitor-token-123',
          },
        },
      });
    },
  });

  const result = await client.bootstrap('https://docs.example.test/install');

  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, 'http://127.0.0.1:8000/api/widget/bootstrap');
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    page_url: 'https://docs.example.test/install',
  });
  assert.equal(result.site.public_key, 'site_public_docs');
  assert.equal(result.visitor.token, 'visitor-token-123');
});

test('starts a conversation and sends the first visitor message', async () => {
  const calls = [];
  const client = Wayfindr.createClient({
    apiBaseUrl: 'http://127.0.0.1:8000',
    sitePublicKey: 'site_public_docs',
    anonymousId: 'anon-browser-123',
    fetch: async (url, options) => {
      calls.push({ url, options });

      if (url.endsWith('/api/widget/bootstrap')) {
        return jsonResponse(200, {
          data: {
            site: {
              public_key: 'site_public_docs',
              settings: {},
            },
            visitor: {
              anonymous_id: 'anon-browser-123',
              token: 'visitor-token-123',
            },
          },
        });
      }

      if (url.endsWith('/api/conversations')) {
        return jsonResponse(201, {
          data: {
            support_code: 'WF-TEST123',
            status: 'open',
          },
        });
      }

      return jsonResponse(201, {
        data: {
          conversation: {
            support_code: 'WF-TEST123',
          },
          message: {
            type: 'text',
            body: 'Can you help me?',
          },
        },
      });
    },
  });

  const result = await client.sendFirstMessage('Can you help me?', {
    pageUrl: 'https://docs.example.test/install',
  });

  assert.equal(calls.length, 3);
  assert.equal(calls[0].url, 'http://127.0.0.1:8000/api/widget/bootstrap');
  assert.equal(calls[1].url, 'http://127.0.0.1:8000/api/conversations');
  assert.deepEqual(JSON.parse(calls[1].options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    visitor_token: 'visitor-token-123',
    subject: 'Can you help me?',
    page_url: 'https://docs.example.test/install',
  });
  assert.equal(calls[2].url, 'http://127.0.0.1:8000/api/conversations/WF-TEST123/messages');
  assert.deepEqual(JSON.parse(calls[2].options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    visitor_token: 'visitor-token-123',
    body: 'Can you help me?',
  });
  assert.equal(result.conversation.support_code, 'WF-TEST123');
  assert.equal(result.message.body, 'Can you help me?');
});

test('fetches visitor-visible conversation messages', async () => {
  const calls = [];
  const client = Wayfindr.createClient({
    apiBaseUrl: 'http://127.0.0.1:8000/',
    sitePublicKey: 'site_public_docs',
    anonymousId: 'anon-browser-123',
    visitorToken: 'visitor-token-123',
    fetch: async (url, options) => {
      calls.push({ url, options });

      return jsonResponse(200, {
        data: {
          conversation: {
            support_code: 'WF-TEST123',
          },
          messages: [
            {
              id: 1,
              sender: {
                kind: 'agent',
                name: 'Ada Agent',
              },
              type: 'text',
              body: 'Hello from support.',
              created_at: '2026-05-23T13:00:00.000000Z',
            },
          ],
        },
      });
    },
  });

  const result = await client.fetchMessages('WF-TEST123');

  assert.equal(calls.length, 1);
  assert.equal(
    calls[0].url,
    'http://127.0.0.1:8000/api/conversations/WF-TEST123/messages?site_public_key=site_public_docs&anonymous_id=anon-browser-123&visitor_token=visitor-token-123',
  );
  assert.equal(calls[0].options.method, 'GET');
  assert.equal(result.conversation.support_code, 'WF-TEST123');
  assert.equal(result.messages[0].sender.kind, 'agent');
  assert.equal(result.messages[0].body, 'Hello from support.');
});

test('renders the embedded conversation timeline and refreshes replies', async () => {
  const dom = new JSDOM('<!doctype html><html><head></head><body><div id="support"></div></body></html>', {
    url: 'https://docs.example.test/install',
  });
  const calls = [];
  let timelineFetches = 0;

  const widget = Wayfindr.init({
    document: dom.window.document,
    location: dom.window.location,
    mount: '#support',
    apiBaseUrl: 'http://127.0.0.1:8000/',
    sitePublicKey: 'site_public_docs',
    anonymousId: 'anon-browser-123',
    storage: memoryStorage(),
    fetch: async (url, options) => {
      calls.push({ url, options });

      if (url.endsWith('/api/widget/bootstrap')) {
        return jsonResponse(201, {
          data: {
            site: { public_key: 'site_public_docs', settings: {} },
            visitor: { anonymous_id: 'anon-browser-123', token: 'visitor-token-123' },
          },
        });
      }

      if (url.endsWith('/api/conversations')) {
        return jsonResponse(201, {
          data: {
            support_code: 'WF-TEST123',
            status: 'open',
          },
        });
      }

      if (url.endsWith('/api/conversations/WF-TEST123/messages')) {
        return jsonResponse(201, {
          data: {
            conversation: { support_code: 'WF-TEST123' },
            message: {
              sender: { kind: 'visitor', name: 'Visitor' },
              type: 'text',
              body: 'Can you help me?',
              created_at: '2026-05-23T14:00:00.000000Z',
            },
          },
        });
      }

      timelineFetches += 1;

      return jsonResponse(200, {
        data: {
          conversation: {
            support_code: 'WF-TEST123',
            status: 'open',
          },
          messages: [
            {
              id: 1,
              sender: { kind: 'visitor', name: 'Visitor' },
              type: 'text',
              body: 'Can you help me?',
              created_at: '2026-05-23T14:00:00.000000Z',
            },
            ...(timelineFetches > 1
              ? [{
                  id: 2,
                  sender: { kind: 'agent', name: 'Ada Agent' },
                  type: 'text',
                  body: 'Absolutely, happy to help.',
                  created_at: '2026-05-23T14:01:00.000000Z',
                }]
              : []),
          ],
        },
      });
    },
  });

  widget.open();

  widget.root.querySelector('.wayfindr-widget__textarea').value = 'Can you help me?';
  widget.root.querySelector('.wayfindr-widget__form').dispatchEvent(
    new dom.window.Event('submit', { bubbles: true, cancelable: true }),
  );

  await settle();

  assert.match(
    widget.root.querySelector('.wayfindr-widget__status').textContent,
    /Support code WF-TEST123/,
  );
  assert.deepEqual(JSON.parse(calls.find((call) => call.url.endsWith('/api/conversations')).options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    visitor_token: 'visitor-token-123',
    subject: 'Can you help me?',
    page_url: 'https://docs.example.test/install',
  });
  assert.deepEqual(
    [...widget.root.querySelectorAll('.wayfindr-widget__message')].map((message) => message.textContent),
    ['VisitorCan you help me?'],
  );

  const refresh = widget.root.querySelector('.wayfindr-widget__refresh');

  assert.equal(refresh.hidden, false);

  refresh.dispatchEvent(new dom.window.Event('click', { bubbles: true }));

  await settle();

  assert.deepEqual(
    [...widget.root.querySelectorAll('.wayfindr-widget__message')].map((message) => message.textContent),
    ['VisitorCan you help me?', 'Ada AgentAbsolutely, happy to help.'],
  );
});

function jsonResponse(status, payload) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  };
}

async function settle() {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

function memoryStorage() {
  const values = new Map();

  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
  };
}
