(function (root, factory) {
  var api = factory(root);

  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }

  if (root) {
    root.Wayfindr = api;
  }
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  'use strict';

  var VERSION = '0.0.0';
  var STYLE_ID = 'wayfindr-widget-styles';

  function createClient(options) {
    options = options || {};

    var apiBaseUrl = normalizeApiBaseUrl(options.apiBaseUrl || '');
    var sitePublicKey = options.sitePublicKey;
    var anonymousId = options.anonymousId;
    var fetcher = options.fetch || (root && root.fetch ? root.fetch.bind(root) : null);

    if (!apiBaseUrl) {
      throw new Error('Wayfindr requires an apiBaseUrl option.');
    }

    if (!sitePublicKey) {
      throw new Error('Wayfindr requires a sitePublicKey option.');
    }

    if (!anonymousId) {
      anonymousId = resolveAnonymousId({
        sitePublicKey: sitePublicKey,
        storage: options.storage || (root ? root.localStorage : null),
      });
    }

    if (!fetcher) {
      throw new Error('Wayfindr requires fetch support.');
    }

    return {
      anonymousId: anonymousId,
      sitePublicKey: sitePublicKey,
      bootstrap: function (pageUrl) {
        return postJson(fetcher, apiBaseUrl + '/api/widget/bootstrap', {
          site_public_key: sitePublicKey,
          anonymous_id: anonymousId,
          page_url: pageUrl || null,
        });
      },
      startConversation: function (body, details) {
        details = details || {};

        return postJson(fetcher, apiBaseUrl + '/api/conversations', {
          site_public_key: sitePublicKey,
          anonymous_id: anonymousId,
          subject: details.subject || summarize(body),
          page_url: details.pageUrl || null,
        });
      },
      sendMessage: function (supportCode, body) {
        return postJson(fetcher, apiBaseUrl + '/api/conversations/' + encodeURIComponent(supportCode) + '/messages', {
          site_public_key: sitePublicKey,
          anonymous_id: anonymousId,
          body: body,
        });
      },
      fetchMessages: function (supportCode) {
        return getJson(fetcher, apiBaseUrl + '/api/conversations/' + encodeURIComponent(supportCode) + '/messages?' + toQueryString({
          site_public_key: sitePublicKey,
          anonymous_id: anonymousId,
        }));
      },
      sendFirstMessage: async function (body, details) {
        var conversation = await this.startConversation(body, details);
        var message = await this.sendMessage(conversation.support_code, body);

        return {
          conversation: conversation,
          message: message.message,
        };
      },
    };
  }

  function init(options) {
    options = options || {};

    var doc = options.document || (root ? root.document : null);
    var location = options.location || (root ? root.location : null);

    if (!doc) {
      throw new Error('Wayfindr requires a browser document.');
    }

    var client = createClient({
      apiBaseUrl: options.apiBaseUrl,
      sitePublicKey: options.sitePublicKey,
      anonymousId: options.anonymousId,
      fetch: options.fetch,
      storage: options.storage || (root ? root.localStorage : null),
    });

    injectStyles(doc);

    var mount = resolveMount(doc, options.mount);
    var rootEl = doc.createElement('div');
    rootEl.className = 'wayfindr-widget';
    rootEl.innerHTML = [
      '<button class="wayfindr-widget__launcher" type="button">' + escapeHtml(options.launcherLabel || 'Chat with support') + '</button>',
      '<section class="wayfindr-widget__panel" aria-label="Support chat" hidden>',
      '  <header class="wayfindr-widget__header">',
      '    <strong>' + escapeHtml(options.title || 'Wayfindr Support') + '</strong>',
      '    <button class="wayfindr-widget__close" type="button" aria-label="Close support chat">&times;</button>',
      '  </header>',
      '  <form class="wayfindr-widget__form">',
      '    <label class="wayfindr-widget__label" for="wayfindr-message">How can we help?</label>',
      '    <textarea id="wayfindr-message" class="wayfindr-widget__textarea" name="message" rows="4" required placeholder="' + escapeHtml(options.placeholder || 'Type your message...') + '"></textarea>',
      '    <button class="wayfindr-widget__send" type="submit">Send message</button>',
      '  </form>',
      '  <p class="wayfindr-widget__status" role="status"></p>',
      '</section>',
    ].join('');

    mount.appendChild(rootEl);

    var launcher = rootEl.querySelector('.wayfindr-widget__launcher');
    var panel = rootEl.querySelector('.wayfindr-widget__panel');
    var close = rootEl.querySelector('.wayfindr-widget__close');
    var form = rootEl.querySelector('.wayfindr-widget__form');
    var textarea = rootEl.querySelector('.wayfindr-widget__textarea');
    var status = rootEl.querySelector('.wayfindr-widget__status');
    var send = rootEl.querySelector('.wayfindr-widget__send');
    var bootstrapped = false;

    function open() {
      panel.hidden = false;
      launcher.hidden = true;
      textarea.focus();
    }

    function closePanel() {
      panel.hidden = true;
      launcher.hidden = false;
      launcher.focus();
    }

    launcher.addEventListener('click', open);
    close.addEventListener('click', closePanel);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      var body = textarea.value.trim();

      if (!body) {
        return;
      }

      send.disabled = true;
      status.textContent = 'Sending...';

      try {
        if (!bootstrapped) {
          await client.bootstrap(location ? location.href : null);
          bootstrapped = true;
        }

        var result = await client.sendFirstMessage(body, {
          pageUrl: location ? location.href : null,
        });

        textarea.value = '';
        status.textContent = 'Message sent. Support code ' + result.conversation.support_code + '.';
      } catch (error) {
        status.textContent = error.message || 'Wayfindr could not send that message.';
      } finally {
        send.disabled = false;
      }
    });

    return {
      anonymousId: client.anonymousId,
      client: client,
      root: rootEl,
      open: open,
      close: closePanel,
      destroy: function () {
        rootEl.remove();
      },
    };
  }

  function resolveAnonymousId(options) {
    options = options || {};

    if (options.anonymousId) {
      return options.anonymousId;
    }

    var storage = options.storage;
    var key = 'wayfindr:' + options.sitePublicKey + ':anonymous-id';
    var existing = storageGet(storage, key);

    if (existing) {
      return existing;
    }

    var anonymousId = 'anon_' + randomToken();
    storageSet(storage, key, anonymousId);

    return anonymousId;
  }

  function getJson(fetcher, url) {
    return fetcher(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
      },
    }).then(readJsonResponse);
  }

  function postJson(fetcher, url, payload) {
    return fetcher(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    }).then(readJsonResponse);
  }

  async function readJsonResponse(response) {
    var data = await response.json().catch(function () {
      return {};
    });

    if (!response.ok) {
      throw new Error(data.message || 'Wayfindr request failed with status ' + response.status + '.');
    }

    return data.data;
  }

  function toQueryString(values) {
    return Object.keys(values).map(function (key) {
      return encodeURIComponent(key) + '=' + encodeURIComponent(values[key]);
    }).join('&');
  }

  function normalizeApiBaseUrl(value) {
    return String(value || '').replace(/\/+$/, '');
  }

  function summarize(body) {
    return String(body || '').replace(/\s+/g, ' ').trim().slice(0, 255) || null;
  }

  function randomToken() {
    var crypto = root && root.crypto;

    if (crypto && typeof crypto.randomUUID === 'function') {
      return crypto.randomUUID().replace(/-/g, '');
    }

    return Math.random().toString(36).slice(2) + Date.now().toString(36);
  }

  function storageGet(storage, key) {
    try {
      return storage ? storage.getItem(key) : null;
    } catch (error) {
      return null;
    }
  }

  function storageSet(storage, key, value) {
    try {
      if (storage) {
        storage.setItem(key, value);
      }
    } catch (error) {
      // Private browsing and locked-down embeds can reject storage writes.
    }
  }

  function resolveMount(doc, mount) {
    if (!mount) {
      return doc.body;
    }

    if (typeof mount === 'string') {
      var target = doc.querySelector(mount);

      if (!target) {
        throw new Error('Wayfindr mount target was not found.');
      }

      return target;
    }

    return mount;
  }

  function injectStyles(doc) {
    if (doc.getElementById(STYLE_ID)) {
      return;
    }

    var style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [
      '.wayfindr-widget{position:fixed;right:20px;bottom:20px;z-index:2147483000;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1d2523}',
      '.wayfindr-widget *{box-sizing:border-box}',
      '.wayfindr-widget__launcher,.wayfindr-widget__send{border:0;border-radius:999px;background:#0d6f68;color:#fff;box-shadow:0 12px 30px rgba(8,37,34,.18);cursor:pointer;font:700 14px/1 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}',
      '.wayfindr-widget__launcher{min-height:48px;padding:0 18px}',
      '.wayfindr-widget__send{min-height:40px;padding:0 14px;border-radius:6px}',
      '.wayfindr-widget__launcher:hover,.wayfindr-widget__send:hover{background:#094f4b}',
      '.wayfindr-widget__send:disabled{cursor:wait;opacity:.7}',
      '.wayfindr-widget__panel{width:min(360px,calc(100vw - 32px));border:1px solid #d8dfdc;border-radius:8px;background:#fff;box-shadow:0 20px 55px rgba(8,37,34,.2);overflow:hidden}',
      '.wayfindr-widget__header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #d8dfdc;background:#f7f7f3}',
      '.wayfindr-widget__close{border:0;background:transparent;color:#62706b;cursor:pointer;font:700 24px/1 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:0}',
      '.wayfindr-widget__form{display:grid;gap:10px;padding:16px}',
      '.wayfindr-widget__label{font-size:13px;font-weight:700}',
      '.wayfindr-widget__textarea{width:100%;resize:vertical;border:1px solid #d8dfdc;border-radius:6px;padding:10px;color:#1d2523;font:14px/1.4 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}',
      '.wayfindr-widget__textarea:focus{outline:3px solid rgba(13,111,104,.2);border-color:#0d6f68}',
      '.wayfindr-widget__status{min-height:20px;margin:0;padding:0 16px 16px;color:#62706b;font-size:13px}',
      '@media (max-width:480px){.wayfindr-widget{right:12px;bottom:12px}.wayfindr-widget__panel{width:calc(100vw - 24px)}}',
    ].join('');

    doc.head.appendChild(style);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function autoInitFromCurrentScript() {
    var doc = root && root.document;
    var script = doc && doc.currentScript;

    if (!script || !script.dataset || !script.dataset.wayfindrSiteKey) {
      return;
    }

    init({
      apiBaseUrl: script.dataset.wayfindrApiBaseUrl || root.location.origin,
      sitePublicKey: script.dataset.wayfindrSiteKey,
      launcherLabel: script.dataset.wayfindrLauncherLabel,
      title: script.dataset.wayfindrTitle,
    });
  }

  var api = {
    version: VERSION,
    createClient: createClient,
    init: init,
    normalizeApiBaseUrl: normalizeApiBaseUrl,
    resolveAnonymousId: resolveAnonymousId,
  };

  if (typeof setTimeout === 'function') {
    setTimeout(autoInitFromCurrentScript, 0);
  }

  return api;
});
