/** Acceptance against a disposable WordPress site's real core controls and REST API. */
const assert = require('node:assert/strict');

function originFromMappedPort(mappedPort) {
  const ports = new Set(String(mappedPort).trim().split(/\r?\n/).map((address) => {
    const match = address.trim().match(/^(?:0\.0\.0\.0|127\.0\.0\.1|\[::\]|\[::1\]):([0-9]+)$/);
    assert.ok(match, 'Expected this run\'s local Docker port mapping');
    const port = Number(match[1]);
    assert.ok(port > 0 && port <= 65535, 'Expected a valid mapped TCP port');
    return port;
  }));
  assert.equal(ports.size, 1, 'IPv4 and IPv6 must identify the same WordPress port');
  return `http://localhost:${[...ports][0]}`;
}

function isOperationResponse(response, path, method = 'GET') {
  const url = new URL(response.url());
  const route = url.searchParams.get('rest_route') || url.pathname.replace(/^\/wp-json/, '');
  return route === `/anti-spam-for-wordpress/v1/admin/${path}` && response.request().method() === method;
}

function footerField(payload) {
  const field = payload.sections?.flatMap((section) => section.fields || [])
    .find((item) => item.option === 'asfw_footer_text');
  assert.ok(field, 'The real settings schema must expose Footer text');
  assert.equal(field.type, 'text');
  return field;
}

async function operation(page, path, action, method = 'GET') {
  const [response] = await Promise.all([
    page.waitForResponse((candidate) => isOperationResponse(candidate, path, method)),
    action(),
  ]);
  assert.equal(response.status(), 200, `${method} ${path} must succeed through WordPress REST`);
  return response.json();
}

async function main(mappedPort, wordpressVersion) {
  const origin = originFromMappedPort(mappedPort);
  assert.match(wordpressVersion, /^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/);
  const { chromium } = require('playwright');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
  page.setDefaultTimeout(20000);
  page.setDefaultNavigationTimeout(30000);
  const errors = [];
  const failedOperations = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('response', (response) => {
    if (['settings', 'events', 'analytics'].some((path) =>
      isOperationResponse(response, path, response.request().method())) && !response.ok()) {
      failedOperations.push(`${response.request().method()} ${new URL(response.url()).pathname}: ${response.status()}`);
    }
  });
  const settingsUrl = `${origin}/wp-admin/options-general.php?page=anti-spam-for-wordpress-admin-ui&tab=settings`;
  const app = page.locator('#anti-spam-for-wordpress-admin-ui-root');
  const footer = app.getByRole('textbox', { name: 'Footer text', exact: true });
  const save = app.getByRole('button', { name: 'Save Settings', exact: true }).last();
  let originalValue;
  let needsRestore = false;
  let failure;

  async function saveFooter(value) {
    await footer.fill(value);
    const saved = await operation(page, 'settings', () => save.click(), 'POST');
    assert.ok(saved.updated.includes('asfw_footer_text'));
    assert.equal(footerField(saved.settings).value, value);
    await app.getByText(/^Settings saved\./).waitFor();
  }

  try {
    await page.goto(`${origin}/wp-login.php`);
    assert.equal(new URL(page.url()).origin, origin, 'Login must stay inside the owned local environment');
    // Fresh-install login placement is disabled. Honor that production default;
    // do not suppress security hooks or alter settings to make authentication pass.
    assert.equal(await page.locator('asfw-widget').count(), 0);
    await page.locator('#user_login').fill('admin');
    await page.locator('#user_pass').fill('password');
    await Promise.all([
      page.waitForURL((url) => url.origin === origin && url.pathname.startsWith('/wp-admin/')),
      page.locator('#wp-submit').click(),
    ]);

    const initial = await operation(page, 'settings', () => page.goto(settingsUrl));
    await footer.waitFor();
    originalValue = String(footerField(initial).value ?? '');
    assert.equal(await footer.inputValue(), originalValue);
    await app.getByRole('heading', { name: 'Anti Spam for WordPress', exact: true }).waitFor();

    const runtime = await page.evaluate(() => ({
      apiFetch: typeof window.wp?.apiFetch,
      textControl: Boolean(window.wp?.components?.TextControl),
      createElement: typeof window.wp?.element?.createElement,
      translate: typeof window.wp?.i18n?.__,
      scripts: ['wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n'].map((handle) => {
        const source = document.getElementById(`${handle}-js`)?.src;
        return { handle, source: source ? new URL(source).pathname : '' };
      }),
      componentStyles: Boolean(document.getElementById('wp-components-css')?.sheet?.cssRules.length),
    }));
    for (const key of ['apiFetch', 'createElement', 'translate']) {
      assert.equal(runtime[key], 'function', `WordPress must supply ${key}`);
    }
    assert.equal(runtime.textControl, true, 'WordPress must supply the rendered TextControl');
    for (const script of runtime.scripts) {
      assert.match(script.source, /^\/wp-includes\/js\/dist\//, `${script.handle} must load from WordPress core`);
    }
    assert.equal(runtime.componentStyles, true, 'Real WordPress component styles must load');

    const changedValue = `Integration browser WordPress ${wordpressVersion}`;
    assert.notEqual(originalValue, changedValue);
    needsRestore = true; // A failed response can still represent a committed write.
    await saveFooter(changedValue);
    const reloaded = await operation(page, 'settings', () => page.reload());
    assert.equal(footerField(reloaded).value, changedValue);
    await footer.waitFor();
    assert.equal(await footer.inputValue(), changedValue);

    const events = await operation(page, 'events', () => app.getByRole('tab', { name: 'Events', exact: true }).click());
    assert.ok(Array.isArray(events.items));
    assert.ok(Number.isInteger(events.pagination.page));
    await app.getByRole('button', { name: 'Apply filters', exact: true }).waitFor();
    await app.getByRole('columnheader', { name: 'Time', exact: true }).waitFor();

    const analytics = await operation(page, 'analytics', () => app.getByRole('tab', { name: 'Analytics', exact: true }).click());
    assert.ok(Number.isInteger(analytics.sample.analyzed_events));
    assert.ok(Array.isArray(analytics.daily_challenges));
    await app.getByText(/^Events analyzed:/).waitFor();
    await app.getByText('Challenges Issued by Day', { exact: true }).waitFor();
  } catch (error) {
    failure = error;
  } finally {
    if (needsRestore) {
      try {
        await operation(page, 'settings', () => page.goto(settingsUrl));
        await footer.waitFor();
        await saveFooter(originalValue);
        const restored = await operation(page, 'settings', () => page.reload());
        assert.equal(footerField(restored).value, originalValue);
        await footer.waitFor();
        assert.equal(await footer.inputValue(), originalValue);
      } catch (restoreError) {
        failure = failure ? new AggregateError([failure, restoreError], 'Acceptance and settings restoration failed') : restoreError;
      }
    }
    await browser.close();
  }
  if (failure) {
    console.error({ pageErrors: errors, failedOperations });
    throw failure;
  }
  assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
  assert.deepEqual(failedOperations, [], 'No failed admin REST responses');
  console.log(`PASS WordPress ${wordpressVersion}: real core controls, settings save/reload/restore, Events, Analytics, REST and script handles.`);
}

module.exports = { originFromMappedPort, isOperationResponse };
if (require.main === module) {
  main(process.argv[2], process.argv[3]).catch((error) => {
    console.error(error);
    process.exitCode = 1;
  });
}
