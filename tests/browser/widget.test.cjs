const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');

let browser, server, origin;
before(async () => {
  server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/challenge') {
      const salt = `test?expires=${Math.floor(Date.now() / 1000) + 60}`;
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify({ algorithm: 'SHA-256', salt, maxnumber: 10,
        challenge: createHash('sha256').update(salt + '2').digest('hex'), signature: 'fixture' }));
      return;
    }
    if (url.pathname.startsWith('/public/')) {
      const filename = path.join(__dirname, '../../public', path.basename(url.pathname));
      res.setHeader('Content-Type', filename.endsWith('.css') ? 'text/css' : 'text/javascript');
      res.end(fs.readFileSync(filename));
      return;
    }
    res.setHeader('Content-Type', 'text/html');
    res.setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self'");
    res.end(`<!doctype html><html lang="en"><body><form>
      <input name="email"><asfw-widget name="proof" layout="extended" challengeurl="/challenge"
      data-asfw-privacy-url="/privacy"></asfw-widget><button type="submit">Submit</button></form>
      <script type="module" src="/public/asfw-widget.js?ver=fixture"></script></body></html>`);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch();
});
after(async () => {
  await browser?.close();
  await new Promise(resolve => server.close(resolve));
});
async function pageFor(t) {
  const page = await browser.newPage();
  t.after(() => page.close());
  await page.goto(origin);
  await page.waitForFunction(() => {
    const button = document.querySelector('asfw-widget')?.shadowRoot?.querySelector('button');
    return button && getComputedStyle(button).display === 'flex';
  });
  return page;
}
async function appearance(page) {
  return page.evaluate(() => {
    const root = document.querySelector('asfw-widget').shadowRoot;
    return Object.fromEntries(['.asfw-widget', '.asfw-control', '.asfw-label', '.asfw-footer-link', '.asfw-indicator'].map(selector => {
      const element = root.querySelector(selector), css = getComputedStyle(element);
      return [selector, Object.fromEntries(['backgroundColor','color','fontFamily','fontSize','lineHeight',
        'padding','borderRadius','boxSizing','minHeight','textTransform','letterSpacing','opacity'].map(key => [key, css[key]]))];
    }));
  });
}
test('external CSS, including important rules and root font size, cannot restyle the card', async t => {
  const page = await pageFor(t);
  const baseline = await appearance(page);
  // Serve the hostile fixture as external CSS to keep the strict CSP in force.
  await page.route('**/hostile.css', route => route.fulfill({ contentType: 'text/css', body: `
    html { font-size: 30px; } body { font: italic 30px/3 serif; color: magenta; letter-spacing: 5px; text-transform: uppercase; }
    form button, form a, form span, form svg { background: blue !important; color: yellow !important;
      padding: 40px !important; font: 900 40px/3 serif !important; border-radius: 50px !important; }
    form button:disabled { opacity: .1 !important; } form *::before { content: 'unwanted'; }
    asfw-widget { --asfw-surface: pink; --asfw-text: yellow; }
  ` }));
  await page.evaluate(() => new Promise(resolve => {
    const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = '/hostile.css';
    link.onload = resolve; document.head.append(link);
  }));
  assert.deepEqual(await appearance(page), baseline);
  await page.evaluate(() => document.querySelector('asfw-widget').setState('verifying'));
  assert.equal((await appearance(page))['.asfw-control'].opacity, '1');
  await page.evaluate(() => document.querySelector('asfw-widget').configure({ appearance: 'dark' }));
  assert.equal((await appearance(page))['.asfw-widget'].backgroundColor, 'rgb(17, 24, 39)');
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.shadowRoot.querySelector('link').href), origin + '/public/asfw-widget-internal.css?ver=fixture');
});
test('real proof solving stays in FormData, with reset, rename and reconnection', async t => {
  const page = await pageFor(t);
  await page.locator('asfw-widget .asfw-control').click();
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  const result = await page.evaluate(() => {
    const widget = document.querySelector('asfw-widget'), form = widget.closest('form');
    const values = new FormData(form).getAll('proof');
    const payload = JSON.parse(atob(values[0]));
    widget.configure({ name: 'renamed' });
    const renamed = new FormData(form).has('renamed') && !new FormData(form).has('proof');
    widget.remove(); form.append(widget); widget.reset();
    return { count: values.length, number: payload.number, renamed, state: widget.getState(),
      cleared: new FormData(form).get('renamed'), inputs: widget.querySelectorAll('input').length };
  });
  assert.deepEqual(result, { count: 1, number: '2', renamed: true, state: 'idle', cleared: '', inputs: 1 });
});
test('automatic submission preserves its submitter and includes proof; missing verification focuses the control', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.addEventListener('submit', event => {
      event.preventDefault();
      if (document.querySelector('asfw-widget').getState() === 'verified') {
        window.submitted = { proof: !!new FormData(form).get('proof'), submitter: event.submitter.textContent };
      }
    });
  });
  await page.locator('form > button').click();
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.shadowRoot.activeElement === el.shadowRoot.querySelector('button')), true);
  await page.evaluate(() => document.querySelector('asfw-widget').configure({ auto: 'onsubmit' }));
  await page.locator('form > button').click();
  await page.waitForFunction(() => window.submitted);
  assert.deepEqual(await page.evaluate(() => window.submitted), { proof: true, submitter: 'Submit' });
});
test('narrow RTL layouts, hidden decorations, reduced motion and focus remain usable', async t => {
  const page = await pageFor(t);
  await page.setViewportSize({ width: 280, height: 800 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.evaluate(() => {
    document.documentElement.dir = 'rtl';
    document.querySelector('asfw-widget').configure({ hidelogo: '1', strings: {
      label: 'A long translated verification label that needs several lines',
      footer: 'A longer translated protection footer that must wrap'
    }});
  });
  const layout = await page.locator('asfw-widget').evaluate(el => {
    const root = el.shadowRoot, card = root.querySelector('.asfw-widget');
    return { overflow: card.scrollWidth > card.clientWidth,
      direction: getComputedStyle(root.querySelector('.asfw-control')).direction,
      logo: getComputedStyle(root.querySelector('.asfw-footer-icon')).display };
  });
  assert.deepEqual(layout, { overflow: false, direction: 'rtl', logo: 'none' });
  await page.evaluate(() => document.querySelector('asfw-widget').setState('verifying'));
  assert.equal(await page.locator('asfw-widget .asfw-spinner').evaluate(el => getComputedStyle(el).animationName), 'none');
  await page.emulateMedia({ forcedColors: 'active' });
  await page.evaluate(() => document.querySelector('asfw-widget').reset());
  await page.locator('asfw-widget .asfw-control').focus();
  assert.equal(await page.locator('asfw-widget .asfw-control').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
});
test('challenge failures expose an error and never serialize a proof', async t => {
  const page = await pageFor(t);
  await page.route('**/challenge', route => route.fulfill({ status: 503, body: '' }));
  await page.locator('asfw-widget .asfw-control').click();
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'error');
  assert.equal(await page.locator('.asfw-error').isVisible(), true);
  assert.equal(await page.evaluate(() => new FormData(document.querySelector('form')).get('proof')), '');
});

test('dynamic widgets have independent state and onfocus works across the shadow boundary', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const first = document.querySelector('asfw-widget');
    first.configure({ auto: 'onfocus', layout: 'compact', hidefooter: '1' });
    const second = document.createElement('asfw-widget');
    second.setAttribute('name', 'second');
    second.setAttribute('challengeurl', '/challenge');
    first.after(second);
  });
  await page.locator('input[name=email]').focus();
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  assert.equal(await page.locator('asfw-widget').nth(1).evaluate(el => el.getState()), 'idle');
  assert.equal(await page.locator('asfw-widget').first().locator('.asfw-intro').isVisible(), false);
  assert.equal(await page.locator('asfw-widget').first().locator('.asfw-footer').isVisible(), false);
});

test('stylesheet failure leaves an accessible error and blocks automatic verification', async t => {
  const page = await browser.newPage();
  t.after(() => page.close());
  await page.route('**/asfw-widget-internal.css*', route => route.abort());
  await page.goto(origin);
  await page.waitForFunction(() => document.querySelector('asfw-widget')?.shadowRoot?.querySelector('[role=alert]'));
  const verified = await page.locator('asfw-widget').evaluate(el => el.startVerification());
  assert.equal(verified, false);
  assert.equal(await page.locator('asfw-widget [role=alert]').isVisible(), true);
  assert.equal(await page.evaluate(() => new FormData(document.querySelector('form')).get('proof')), '');
});
