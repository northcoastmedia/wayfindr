const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

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
});

test('starts a conversation and sends the first visitor message', async () => {
  const calls = [];
  const client = Wayfindr.createClient({
    apiBaseUrl: 'http://127.0.0.1:8000',
    sitePublicKey: 'site_public_docs',
    anonymousId: 'anon-browser-123',
    fetch: async (url, options) => {
      calls.push({ url, options });

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

  assert.equal(calls.length, 2);
  assert.equal(calls[0].url, 'http://127.0.0.1:8000/api/conversations');
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    subject: 'Can you help me?',
    page_url: 'https://docs.example.test/install',
  });
  assert.equal(calls[1].url, 'http://127.0.0.1:8000/api/conversations/WF-TEST123/messages');
  assert.deepEqual(JSON.parse(calls[1].options.body), {
    site_public_key: 'site_public_docs',
    anonymous_id: 'anon-browser-123',
    body: 'Can you help me?',
  });
  assert.equal(result.conversation.support_code, 'WF-TEST123');
  assert.equal(result.message.body, 'Can you help me?');
});

function jsonResponse(status, payload) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  };
}
