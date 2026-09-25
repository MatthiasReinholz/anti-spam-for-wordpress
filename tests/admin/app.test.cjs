const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
let browser, server, origin;
const settings = (first = 'initial', second = 'other') => ({
  sections: [{ id: 'general', title: 'General', fields: [
    { id: 'first', option: 'asfw_first', type: 'text', label: 'First setting', value: first },
    { id: 'second', option: 'asfw_second', type: 'text', label: 'Second setting', value: second },
  ] }], summary: { rows: [] }, privacy_policy_text: { text: 'Suggested policy' },
});
const events = (label = 'latest', page = 1) => ({ logging_enabled: true,
  items: [{ id: page, context: label, event_type: 'verified' }], pagination: { page, total_pages: 3 },
});
const json = (route, data, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });

before(async () => {
  assert.ok(fs.existsSync(path.join(root, 'assets/admin-ui/index.js')), 'Build admin assets before running these tests.');
  server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const files = {
      '/react.js': '.wp-plugin-base-admin-ui/node_modules/react/umd/react.development.js',
      '/react-dom.js': '.wp-plugin-base-admin-ui/node_modules/react-dom/umd/react-dom.development.js',
      '/runtime.js': 'tests/admin/fixtures/wp-runtime.js',
      '/admin.js': 'assets/admin-ui/index.js',
    };
    if (files[url.pathname]) {
      res.setHeader('Content-Type', 'text/javascript');
      res.end(fs.readFileSync(path.join(root, files[url.pathname])));
      return;
    }
    if (url.pathname.startsWith('/api/')) {
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify(url.pathname.endsWith('/settings') ? settings()
        : url.pathname.endsWith('/events') ? events('latest', Number(url.searchParams.get('page_number') || 1))
          : { logging_enabled: true, sample: { analyzed_events: 12, total_events: 12 } }));
      return;
    }
    res.setHeader('Content-Type', 'text/html');
    res.end('<!doctype html><html lang="en"><body><main id="app"></main><script src="/react.js"></script><script src="/react-dom.js"></script><script src="/runtime.js"></script><script src="/admin.js"></script></body></html>');
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch();
});
after(async () => { await browser?.close(); await new Promise(resolve => server.close(resolve)); });
async function pageFor(t, tab = 'settings', beforeNavigation = async () => {}) {
  const page = await browser.newPage();
  t.after(() => page.close());
  await beforeNavigation(page);
  await page.goto(`${origin}/?tab=${tab}`);
  await page.getByRole('tab', { name: 'Settings', exact: true }).waitFor();
  return page;
}
async function waitCalls(page, count) { await page.waitForFunction(count => window.adminCalls.length >= count, count); }

test('one operation request per activation, applied filter and explicit refresh, with one namespace', async t => {
  const page = await pageFor(t, 'events');
  await page.getByText('latest', { exact: true }).waitFor();
  assert.equal(await page.evaluate(() => window.adminCalls.length), 1);
  await page.getByLabel('Context', { exact: true }).fill('wordpress:login');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await waitCalls(page, 2);
  await page.getByRole('button', { name: 'Refresh', exact: true }).click();
  await waitCalls(page, 3);
  await page.getByRole('tab', { name: 'Analytics', exact: true }).click();
  await page.getByText('Events analyzed: 12 / 12', { exact: true }).waitFor({ timeout: 3000 }).catch(async error => { t.diagnostic(await page.locator('body').innerText()); t.diagnostic(JSON.stringify(await page.evaluate(() => window.adminCalls))); throw error; });
  await page.getByRole('button', { name: 'Refresh', exact: true }).click();
  await waitCalls(page, 5);
  const calls = await page.evaluate(() => window.adminCalls);
  assert.equal(calls.length, 5);
  calls.forEach(call => assert.equal(call.path.split('anti-spam-for-wordpress/v1').length, 2));
  assert.equal(calls[0].path, '/anti-spam-for-wordpress/v1/admin/events?page_number=1&per_page=50');
  assert.equal(calls[1].path, '/anti-spam-for-wordpress/v1/admin/events?context=wordpress%3Alogin&page_number=1&per_page=50');
  assert.equal(calls[2].path, calls[1].path);
  assert.equal(calls[3].path, '/anti-spam-for-wordpress/v1/admin/analytics?context=wordpress%3Alogin');
  assert.equal(calls[4].path, calls[3].path);
});

for (const [tab, status] of [['settings', 403], ['events', 404], ['analytics', 500]]) {
  test(`${tab} ${status} errors stop until explicit retry`, async t => {
    const page = await pageFor(t, tab, async page => {
      await page.route('**/api/**', route => json(route, { message: 'Fixture request failed' }, status));
    });
    await page.getByText('Fixture request failed', { exact: false }).waitFor();
    await page.waitForTimeout(80);
    assert.equal(await page.evaluate(() => window.adminCalls.length), 1);
    await page.getByRole('button', { name: 'Try again', exact: true }).click();
    await waitCalls(page, 2);
    await page.waitForTimeout(50);
    assert.equal(await page.evaluate(() => window.adminCalls.length), 2);
    await page.unroute('**/api/**');
    await page.getByRole('button', { name: 'Try again', exact: true }).click();
    await waitCalls(page, 3);
    await page.getByRole('button', { name: 'Try again', exact: true }).waitFor({ state: 'hidden' });
    assert.equal(await page.evaluate(() => window.adminCalls.length), 3);
  });
}

test('an older page cannot replace a newly applied query, even if transport ignores abort', async t => {
  let oldRequest;
  const page = await pageFor(t, 'events', async page => {
    await page.addInitScript(() => { window.ignoreAdminAbort = true; });
    await page.route('**/api/**/events?**', route => {
      const url = new URL(route.request().url());
      if (url.searchParams.get('page_number') === '2') { oldRequest = route; return; }
      return json(route, events(url.searchParams.get('context') || 'initial'));
    });
  });
  await page.getByText('initial', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await waitCalls(page, 2);
  await page.getByLabel('Context', { exact: true }).fill('new query');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await page.getByText('new query', { exact: true }).waitFor();
  await json(oldRequest, events('stale page', 2));
  await page.waitForTimeout(50);
  assert.equal(await page.getByText('stale page', { exact: true }).count(), 0);
  assert.equal(await page.getByText('new query', { exact: true }).count(), 1);
  assert.equal(await page.evaluate(() => window.adminCalls.length), 3);
});

test('save merges normalized unchanged fields, retains newer edits, and sends one mutation', async t => {
  let pending;
  const page = await pageFor(t, 'settings', async page => {
    await page.route('**/api/**/settings', route => {
      if (route.request().method() === 'POST') { pending = route; return; }
      return json(route, settings());
    });
  });
  await page.getByLabel('First setting', { exact: true }).fill('submitted');
  await page.getByLabel('Second setting', { exact: true }).fill(' normalized ');
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });
  await waitCalls(page, 2);
  assert.equal(await page.getByRole('button', { name: 'Save Settings', exact: true }).isDisabled(), true);
  await page.getByLabel('First setting', { exact: true }).fill('new unsaved edit');
  await json(pending, { settings: settings('submitted', 'normalized') });
  await page.getByText('Settings saved.', { exact: true }).waitFor();
  assert.equal(await page.getByLabel('First setting', { exact: true }).inputValue(), 'new unsaved edit');
  assert.equal(await page.getByLabel('Second setting', { exact: true }).inputValue(), 'normalized');
  const calls = await page.evaluate(() => window.adminCalls);
  assert.equal(calls.filter(call => call.method === 'POST').length, 1);
  assert.equal(calls[1].data.values.asfw_first, 'submitted');
  await page.getByRole('tab', { name: 'Events', exact: true }).click();
  await page.getByText('latest', { exact: true }).waitFor();
  await page.getByRole('tab', { name: 'Settings', exact: true }).click();
  assert.equal(await page.getByLabel('First setting', { exact: true }).inputValue(), 'new unsaved edit');
});

test('save failure preserves the draft and later explicit retry can succeed', async t => {
  const page = await pageFor(t, 'settings', async page => {
    await page.route('**/api/**/settings', route => json(route,
      route.request().method() === 'POST' ? { message: 'Save failed' } : settings(),
      route.request().method() === 'POST' ? 500 : 200));
  });
  await page.getByLabel('First setting', { exact: true }).fill('keep my edit');
  await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
  await page.getByText('Save failed', { exact: true }).waitFor();
  assert.equal(await page.getByLabel('First setting', { exact: true }).inputValue(), 'keep my edit');
  assert.equal(await page.getByRole('button', { name: 'Save Settings', exact: true }).isEnabled(), true);
  await page.unroute('**/api/**/settings');
  await page.route('**/api/**/settings', route => json(route, { settings: settings('keep my edit') }));
  await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
  await page.getByText('Settings saved.', { exact: true }).waitFor();
});

for (const ignoreAbort of [false, true]) {
  test(`save timeout retains edits and permits an explicit retry (transport ignores abort: ${ignoreAbort})`, async t => {
    let firstRequest;
    let posts = 0;
    const page = await pageFor(t, 'settings', async page => {
      await page.addInitScript(value => { window.ignoreAdminAbort = value; }, ignoreAbort);
      await page.route('**/api/**/settings', route => {
        if (route.request().method() !== 'POST') return json(route, settings());
        posts++;
        if (posts === 1) { firstRequest = route; return; }
        return json(route, { settings: settings('retry value') });
      });
    });
    await page.getByLabel('First setting', { exact: true }).fill('first attempt');
    await page.clock.install();
    await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
    await waitCalls(page, 2);
    await page.getByLabel('First setting', { exact: true }).fill('retry value');
    await page.clock.fastForward(30001);
    await page.getByText('The save request timed out.', { exact: false }).waitFor();
    assert.equal(await page.getByLabel('First setting', { exact: true }).inputValue(), 'retry value');
    assert.equal(await page.getByRole('button', { name: 'Save Settings', exact: true }).isEnabled(), true);
    assert.equal(posts, 1, 'Timeout must not automatically repeat the mutation.');
    await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
    await page.getByText('Settings saved.', { exact: true }).waitFor();
    assert.equal(posts, 2);
    if (ignoreAbort) {
      await json(firstRequest, { settings: settings('stale first attempt') });
      await page.waitForTimeout(30);
      assert.equal(await page.getByLabel('First setting', { exact: true }).inputValue(), 'retry value');
      assert.equal(await page.getByText('Settings saved.', { exact: true }).count(), 1);
    }
    assert.deepEqual(await page.evaluate(() => window.adminFailures), []);
  });
}

for (const unavailable of [false, true]) {
  test(`privacy text copy failure offers a manual fallback and can recover (API unavailable: ${unavailable})`, async t => {
    const page = await pageFor(t, 'settings', async page => {
      await page.addInitScript(value => {
        Object.defineProperty(window.navigator, 'clipboard', { configurable: true, value: value ? undefined : {
          writeText: () => Promise.reject(new DOMException('Permission denied', 'NotAllowedError')),
        } });
      }, unavailable);
    });
    await page.getByRole('button', { name: 'Copy text', exact: true }).click();
    await page.getByText('Automatic copying is unavailable. Select the suggested text and copy it manually.', { exact: true }).waitFor();
    assert.equal(await page.getByLabel('Suggested text', { exact: false }).inputValue(), 'Suggested policy');
    assert.deepEqual(await page.evaluate(() => window.adminFailures), []);
    await page.evaluate(() => {
      Object.defineProperty(window.navigator, 'clipboard', { configurable: true, value: {
        writeText: async text => { window.copiedPolicy = text; },
      } });
    });
    await page.clock.install();
    await page.getByRole('button', { name: 'Copy text', exact: true }).click();
    await page.getByRole('button', { name: 'Copied', exact: true }).waitFor();
    assert.equal(await page.evaluate(() => window.copiedPolicy), 'Suggested policy');
    assert.equal(await page.getByText('Automatic copying is unavailable.', { exact: false }).count(), 0);
    await page.clock.fastForward(2001);
    await page.getByRole('button', { name: 'Copy text', exact: true }).waitFor();
  });
}

test('unmount ignores a pending save result without claiming that the mutation was canceled', async t => {
  let pending;
  const page = await pageFor(t, 'settings', async page => {
    await page.route('**/api/**/settings', route => {
      if (route.request().method() === 'POST') { pending = route; return; }
      return json(route, settings());
    });
  });
  await page.getByRole('button', { name: 'Save Settings', exact: true }).click();
  await waitCalls(page, 2);
  await page.evaluate(() => window.adminRoot.unmount());
  await json(pending, { settings: settings('committed') });
  await page.waitForTimeout(30);
  assert.deepEqual(await page.evaluate(() => window.adminFailures), []);
  assert.equal(await page.locator('#app').textContent(), '');
});
