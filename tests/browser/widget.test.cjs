const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { chromium, firefox, webkit } = require('playwright');
const browserType = { chromium, firefox, webkit }[process.env.ASFW_BROWSER || 'chromium'];
assert.ok(browserType, 'ASFW_BROWSER must be chromium, firefox, or webkit');
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
    if (url.pathname === '/jquery.js') {
      res.setHeader('Content-Type', 'text/javascript');
      res.end(fs.readFileSync(require.resolve('jquery/dist/jquery.min.js')));
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
      <script src="/jquery.js"></script><script src="/public/script.js"></script><script type="module" src="/public/asfw-widget.js?ver=fixture"></script></body></html>`);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await browserType.launch();
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

test('stalled stylesheet exposes a bounded failure and can recover through retry', async t => {
  const page = await browser.newPage();
  t.after(() => page.close());
  await page.clock.install();
  let firstRequest;
  let notifyRequest;
  const requestStarted = new Promise(resolve => { notifyRequest = resolve; });
  let requests = 0;
  await page.route('**/asfw-widget-internal.css*', async route => {
    requests += 1;
    if (requests === 1) {
      firstRequest = route;
      notifyRequest();
      return;
    }
    await route.continue();
  });
  await page.goto(origin, { waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => !!document.querySelector('asfw-widget')?.shadowRoot);
  await requestStarted;
  await page.clock.fastForward(10001);
  const retry = page.getByRole('button', { name: 'Try again' });
  assert.equal(await page.getByRole('alert').isVisible(), true);
  assert.equal(await retry.isEnabled(), true);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), false);
  await firstRequest.abort();
  await retry.click();
  await page.locator('asfw-widget .asfw-control').waitFor({ state: 'visible' });
  assert.equal(await page.getByRole('alert').isVisible(), false);
  await page.locator('asfw-widget .asfw-control').click();
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  assert.equal(await page.evaluate(() => !!new FormData(document.querySelector('form')).get('proof')), true);
});

for (const operation of ['reset', 'challenge change']) {
  test(`${operation} invalidates pending verification without overwriting a newer result`, async t => {
    const page = await pageFor(t);
    let releaseRequest;
    const requestSeen = new Promise(resolve => { releaseRequest = resolve; });
    let held = false;
    await page.route('**/challenge', route => {
      if (held) return route.continue();
      held = true;
      releaseRequest(route);
    });
    await page.evaluate(() => {
      window.oldVerification = document.querySelector('asfw-widget').startVerification();
    });
    const oldRequest = await requestSeen;
    await page.locator('asfw-widget').evaluate((el, operation) => {
      if (operation === 'reset') el.reset();
      else el.configure({ challengeurl: '/challenge?new=1' });
    }, operation);
    assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
    const currentProof = await page.evaluate(() => new FormData(document.querySelector('form')).get('proof'));
    if (operation === 'reset') {
      const salt = `stale?expires=${Math.floor(Date.now() / 1000) + 60}`;
      await oldRequest.fulfill({ contentType: 'application/json', body: JSON.stringify({
        algorithm: 'SHA-256', salt, maxnumber: 10,
        challenge: createHash('sha256').update(salt + '2').digest('hex'), signature: 'stale'
      }) });
    } else {
      await oldRequest.fulfill({ status: 503, body: '' });
    }
    assert.equal(await page.evaluate(() => window.oldVerification), false);
    assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'verified');
    assert.equal(await page.evaluate(() => new FormData(document.querySelector('form')).get('proof')), currentProof);
  });
}

test('shipped catalogs render localized widget states without changing form protection', async t => {
  const { execFileSync } = require('node:child_process');
  const catalogs = JSON.parse(execFileSync('python3', ['-c', `
import gettext, json, pathlib, sys
keys = {'error': 'Verification failed. Try again later.', 'footer': 'Protected by Anti Spam for WordPress',
'intro': 'This check helps prevent spam.', 'label': "I'm not a robot",
'privacy': 'Privacy', 'retry': 'Try again', 'required': 'Please verify before submitting.',
'verified': 'Verified', 'verifying': 'Verifying...', 'waitAlert': 'Verifying... please wait.'}
catalogs = {}
for path in pathlib.Path(sys.argv[1]).glob('*.mo'):
    with path.open('rb') as stream:
        catalog = gettext.GNUTranslations(stream)
    catalogs[path.stem] = {key: catalog.gettext(value) for key, value in keys.items()}
print(json.dumps(catalogs))
`, path.join(__dirname, '../../languages')], { encoding: 'utf8' }));
  assert.ok(Object.keys(catalogs).length >= 17);
  const page = await pageFor(t);
  for (const [locale, strings] of Object.entries(catalogs)) {
    await page.locator('asfw-widget').evaluate((widget, strings) => {
      widget.reset();
      widget.configure({ strings });
    }, strings);
    const widget = page.locator('asfw-widget');
    assert.equal(await widget.locator('.asfw-label').textContent(), strings.label, locale);
    assert.equal(await widget.locator('.asfw-intro').textContent(), strings.intro, locale);
    assert.equal(await widget.locator('.asfw-footer-text').textContent(), strings.footer, locale);
    assert.equal(await widget.locator('.asfw-footer-link').textContent(), strings.privacy, locale);
    assert.equal(await widget.locator('button').last().textContent(), strings.retry, locale);
    await page.locator('button[type="submit"]').click();
    assert.equal(await widget.locator('.asfw-error').textContent(), strings.required, locale);
    await widget.evaluate(widget => widget.setState('verifying'));
    assert.equal(await widget.locator('.asfw-status').textContent(), strings.verifying, locale);
    await page.locator('button[type="submit"]').click();
    assert.equal(await widget.evaluate(el => el.getState()), 'verifying', locale);
    await widget.evaluate(el => el.reset());
    await widget.locator('.asfw-control').click();
    await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
    assert.equal(await widget.locator('.asfw-status').textContent(), strings.verified, locale);
    assert.notEqual(await widget.locator('input[name="proof"]').inputValue(), '', locale);
    await widget.evaluate(widget => widget.setState('error'));
    assert.equal(await widget.locator('.asfw-error').textContent(), strings.error, locale);
    assert.equal(await widget.locator('.asfw-widget-shell').evaluate(el => el.scrollWidth <= el.clientWidth), true, locale);
  }
});


test('translation values stay text and malformed values retain English fallbacks', async t => {
  const page = await pageFor(t);
  const widget = page.locator('asfw-widget');
  const literal = '<img src=x onerror="window.asfwInjected = true">';
  await widget.evaluate((widget, literal) => widget.configure({ strings: {
    label: literal, intro: null, privacy: '', footer: ['bad'], retry: 42,
    required: { text: 'bad' }, verified: false, verifying: '   ', waitAlert: 'Bitte warte kurz.',
  } }), literal);
  assert.equal(await widget.locator('.asfw-label').textContent(), literal);
  assert.equal(await widget.locator('img').count(), 0);
  assert.equal(await page.evaluate(() => window.asfwInjected), undefined);
  const strings = await widget.evaluate(widget => widget.getStrings());
  assert.equal(strings.intro, 'This check helps prevent spam.');
  assert.equal(strings.privacy, 'Privacy');
  assert.equal(strings.footer, 'Protected by Anti Spam for WordPress');
  assert.equal(strings.retry, 'Try again');
  assert.equal(strings.required, 'Please verify before submitting.');
  assert.equal(strings.verified, 'Verified');
  assert.equal(strings.verifying, 'Verifying...');
  assert.equal(strings.waitAlert, 'Bitte warte kurz.');
  for (const invalid of ['{broken', 'null', '[]', '42', '"text"']) {
    await widget.evaluate((widget, invalid) => widget.setAttribute('strings', invalid), invalid);
    assert.equal(await widget.locator('.asfw-label').textContent(), "I'm not a robot");
  }
});

async function addGuards(page, options = {}) {
  await page.evaluate(options => {
    const form = document.querySelector('form');
    if (options.delay !== false) {
      const status = document.createElement('span');
      status.className = 'asfw-submit-delay-status';
      status.dataset.asfwSubmitDelayTokenUrl = '/delay';
      status.dataset.asfwSubmitDelayMode = 'block';
      form.append(status);
    }
    if (options.math) {
      const math = document.createElement('div');
      math.className = 'asfw-math-challenge';
      math.dataset.asfwMathChallengeUrl = '/math';
      math.dataset.asfwMathMode = 'block';
      math.innerHTML = '<label class="asfw-math-question" for="answer">Security check</label><input id="answer" name="asfw_math_answer">';
      form.append(math);
    }
  }, options);
}

async function guardRoutes(page, delayMs = 30) {
  const counts = { delay: 0, math: 0 };
  for (const kind of ['delay', 'math']) {
    await page.route(`**/${kind}`, route => {
      counts[kind] += 1;
      const common = { signature: `signature-${counts[kind]}`, expires_at: Math.floor(Date.now() / 1000) + 600 };
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(kind === 'delay'
        ? { ...common, token_id: `delay-${counts[kind]}`, issued_at_ms: Date.now(), delay_ms: delayMs }
        : { ...common, challenge_id: `math-${counts[kind]}`, left: 2, right: 3 }) });
    });
  }
  return counts;
}

async function guardsReady(page) {
  await page.waitForFunction(() => {
    const form = document.querySelector('form');
    const delay = form.querySelector('.asfw-submit-delay-status');
    const math = form.querySelector('.asfw-math-challenge');
    return (!delay || !!form.querySelector('[name=asfw_submit_delay_token]')?.value) &&
      (!math || !!form.querySelector('[name=asfw_math_challenge]')?.value) && !form.querySelector('button[type=submit]').disabled;
  });
}

test('both production scripts initialize each guard once without observer feedback', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page, 80);
  await page.evaluate(() => {
    window.mutations = 0;
    new MutationObserver(records => { window.mutations += records.length; }).observe(document.body, { childList: true, subtree: true });
  });
  await addGuards(page, { math: true });
  await guardsReady(page);
  await page.waitForTimeout(80);
  assert.deepEqual(counts, { delay: 1, math: 1 });
  assert.ok(await page.evaluate(() => window.mutations < 40));
  assert.equal(await page.locator('.asfw-math-question').textContent(), '2 + 3 = ?');
  assert.equal(await page.locator('.asfw-guard-retry').count(), 2);
  await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: false })));
  assert.deepEqual(counts, { delay: 1, math: 1 });
});

test('failed guards stop retrying, leave an accessible retry, and recover with one new request', async t => {
  const page = await pageFor(t);
  let requests = 0;
  await page.route('**/delay', route => {
    requests += 1;
    return route.fulfill({ status: 503, body: '' });
  });
  await addGuards(page);
  await page.getByRole('button', { name: 'Try again', exact: true }).last().waitFor({ state: 'visible' });
  await page.waitForTimeout(100);
  assert.equal(requests, 1);
  assert.equal(await page.locator('form > button[type=submit]').isEnabled(), true);
  await page.locator('asfw-widget').evaluate(el => el.startVerification());
  await page.evaluate(() => {
    window.submissions = 0;
    document.querySelector('form').addEventListener('submit', event => { event.preventDefault(); window.submissions += 1; });
    document.querySelector('form').requestSubmit();
  });
  assert.equal(await page.evaluate(() => window.submissions), 0);
  assert.equal(await page.locator('.asfw-guard-retry').evaluate(el => document.activeElement === el), true);
  const counts = await guardRoutes(page);
  await page.locator('.asfw-guard-retry').click();
  await guardsReady(page);
  assert.equal(counts.delay, 1);
});

test('stalled guard fetch times out and explicit retry recovers', async t => {
  const page = await pageFor(t);
  await page.clock.install();
  let held;
  await page.route('**/delay', route => { held = route; });
  await addGuards(page);
  await page.waitForFunction(() => document.querySelector('.asfw-submit-delay-status')?.textContent.includes('Preparing'));
  await page.clock.fastForward(10001);
  await page.locator('.asfw-guard-retry').waitFor({ state: 'visible' });
  assert.equal(await page.locator('form > button[type=submit]').isEnabled(), true);
  await held?.abort();
  await guardRoutes(page, 1);
  await page.locator('.asfw-guard-retry').click();
  await page.waitForFunction(() => !!document.querySelector('[name=asfw_submit_delay_token]')?.value);
  await page.clock.fastForward(10);
  await guardsReady(page);
});

test('completion renews proof and guards after delayed serialization and coalesces native reset', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page);
  await addGuards(page, { math: true });
  await guardsReady(page);
  await page.evaluate(() => {
    window.submissions = [];
    document.querySelector('asfw-widget').configure({ auto: 'onsubmit' });
    const form = document.querySelector('form');
    form.addEventListener('submit', event => {
      event.preventDefault();
      const before = [...new FormData(form).entries()];
      setTimeout(() => {
        window.submissions.push({ before, after: [...new FormData(form).entries()] });
        form.reset();
        form.dispatchEvent(new CustomEvent('asfw:submission-complete', { bubbles: true }));
      }, 50);
    });
  });
  for (let submission = 1; submission <= 2; submission += 1) {
    await page.locator('form > button[type=submit]').click();
    await page.waitForFunction(count => window.submissions.length === count, submission);
    await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
    await guardsReady(page);
    const { before, after } = await page.evaluate(() => window.submissions.at(-1));
    assert.deepEqual(after, before);
    assert.ok(Object.fromEntries(before).proof);
    assert.equal(Object.fromEntries(before).asfw_submit_delay_token, `delay-${submission}`);
    assert.equal(Object.fromEntries(before).asfw_math_challenge, `math-${submission}`);
  }
  assert.deepEqual(counts, { delay: 3, math: 3 });
});

test('canceling native reset preserves proof and guard tokens', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page);
  await addGuards(page);
  await guardsReady(page);
  await page.locator('asfw-widget').evaluate(el => el.startVerification());
  const previous = await page.evaluate(() => [...new FormData(document.querySelector('form')).entries()]);
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.addEventListener('reset', event => event.preventDefault(), { once: true });
    form.reset();
  });
  await page.waitForTimeout(30);
  assert.deepEqual(await page.evaluate(() => [...new FormData(document.querySelector('form')).entries()]), previous);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'verified');
  assert.equal(counts.delay, 1);
});

test('bfcache restoration renews all affected credentials', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page);
  await addGuards(page);
  await guardsReady(page);
  await page.locator('asfw-widget').evaluate(el => el.startVerification());
  await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
  await page.waitForFunction(() => document.querySelector('[name=asfw_submit_delay_token]').value === 'delay-2');
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'idle');
  assert.equal(counts.delay, 2);
});

test('multiple widgets share one submit continuation and retain the original submitter', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const first = document.querySelector('asfw-widget');
    first.configure({ auto: 'onsubmit' });
    const second = document.createElement('asfw-widget');
    second.configure({ name: 'second', challengeurl: '/challenge', auto: 'onsubmit', delay: '60' });
    first.after(second);
    window.submissions = [];
    document.querySelector('form').addEventListener('submit', event => {
      event.preventDefault();
      window.submissions.push({ fields: Object.fromEntries(new FormData(event.target)), submitter: event.submitter.textContent });
    });
  });
  await page.locator('form > button[type=submit]').click();
  await page.locator('form > button[type=submit]').click();
  await page.waitForFunction(() => window.submissions.length > 0);
  await page.waitForTimeout(40);
  const submissions = await page.evaluate(() => window.submissions);
  assert.equal(submissions.length, 1);
  assert.ok(submissions[0].fields.proof);
  assert.ok(submissions[0].fields.second);
  assert.equal(submissions[0].submitter, 'Submit');
});

test('native validation failure cannot leave a future submission bypass', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const widget = document.querySelector('asfw-widget');
    widget.configure({ auto: 'onsubmit', delay: '100' });
    const input = document.querySelector('[name=email]');
    input.required = true;
    input.value = 'present';
    window.submissions = 0;
    const form = document.querySelector('form');
    form.addEventListener('submit', event => { event.preventDefault(); window.submissions += 1; });
    form.requestSubmit();
    input.value = '';
  });
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  assert.equal(await page.evaluate(() => window.submissions), 0);
  await page.locator('asfw-widget').evaluate(el => { el.reset(); el.configure({ auto: false }); });
  await page.locator('[name=email]').fill('again');
  await page.locator('form > button[type=submit]').click();
  assert.equal(await page.evaluate(() => window.submissions), 0);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'error');
});

test('moving a pending widget cannot submit either its previous or next form', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const widget = document.querySelector('asfw-widget');
    widget.configure({ auto: 'onsubmit', delay: '100' });
    const oldForm = widget.closest('form');
    const nextForm = document.createElement('form');
    nextForm.innerHTML = '<button type="submit">Next</button>';
    document.body.append(nextForm);
    window.submissions = 0;
    [oldForm, nextForm].forEach(form => form.addEventListener('submit', event => { event.preventDefault(); window.submissions += 1; }));
    oldForm.requestSubmit();
    nextForm.prepend(widget);
  });
  await page.waitForTimeout(150);
  assert.equal(await page.evaluate(() => window.submissions), 0);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'idle');
});

test('disconnect aborts a pending guard and reconnection starts only one replacement', async t => {
  const page = await pageFor(t);
  let oldRoute;
  await page.route('**/delay', route => { oldRoute = route; });
  await addGuards(page);
  await page.waitForFunction(() => document.querySelector('.asfw-submit-delay-status')?.textContent.includes('Preparing'));
  await page.evaluate(() => { window.removedForm = document.querySelector('form'); window.removedForm.remove(); });
  await page.waitForTimeout(10);
  const counts = await guardRoutes(page);
  await page.evaluate(() => document.body.prepend(window.removedForm));
  await guardsReady(page);
  await oldRoute?.fulfill({ status: 503, body: '' });
  assert.equal(counts.delay, 1);
  assert.equal(await page.locator('[name=asfw_submit_delay_token]').inputValue(), 'delay-1');
  assert.equal(await page.locator('.asfw-guard-retry').count(), 1);
});

test('guard completion preserves buttons disabled by the provider', async t => {
  const page = await pageFor(t);
  await guardRoutes(page, 120);
  await addGuards(page);
  await page.waitForFunction(() => !!document.querySelector('[name=asfw_submit_delay_token]')?.value);
  await page.evaluate(() => { document.querySelector('form > button[type=submit]').disabled = true; });
  await page.waitForFunction(() => document.querySelector('.asfw-submit-delay-status').textContent === '');
  assert.equal(await page.locator('form > button[type=submit]').isDisabled(), true);
});

test('CF7 wrapper completion and Gravity initial render affect only the intended form', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const form = document.querySelector('form');
    const wrap = document.createElement('div');
    wrap.className = 'wpcf7';
    form.before(wrap); wrap.append(form);
    form.id = 'gform_7';
    const other = document.createElement('form');
    other.innerHTML = '<asfw-widget name="other" challengeurl="/challenge"></asfw-widget>';
    document.body.append(other);
  });
  await page.locator('asfw-widget').evaluateAll(elements => Promise.all(elements.map(el => el.startVerification())));
  await page.evaluate(() => document.dispatchEvent(new CustomEvent('gform/post_render', { detail: { formId: 7 } })));
  await page.waitForTimeout(20);
  assert.deepEqual(await page.locator('asfw-widget').evaluateAll(elements => elements.map(el => el.getState())), ['verified', 'verified']);
  await page.evaluate(() => document.querySelector('.wpcf7').dispatchEvent(new CustomEvent('wpcf7submit', { bubbles: true })));
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
  assert.equal(await page.locator('asfw-widget').nth(1).evaluate(el => el.getState()), 'verified');
});

test('a proof expiring during the minimum delay is never published', async t => {
  const page = await pageFor(t);
  await page.clock.install();
  await page.locator('asfw-widget').evaluate(el => el.configure({ delay: '2000' }));
  await page.route('**/challenge', route => {
    const salt = `expiring?expires=${Math.floor(Date.now() / 1000) + 1}`;
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ algorithm: 'SHA-256', salt, maxnumber: 10,
      challenge: createHash('sha256').update(salt + '2').digest('hex'), signature: 'fixture' }) });
  });
  await page.evaluate(() => { window.verification = document.querySelector('asfw-widget').startVerification(); });
  await page.waitForFunction(() => !!document.querySelector('asfw-widget')._challenge);
  await page.clock.fastForward(3000);
  assert.equal(await page.evaluate(() => window.verification), false);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'error');
  assert.equal(await page.locator('input[name=proof]').inputValue(), '');
});

test('challenge request timeout is recoverable without a stale result', async t => {
  const page = await pageFor(t);
  await page.clock.install();
  let held;
  await page.route('**/challenge', route => { held = route; });
  await page.evaluate(() => { window.verification = document.querySelector('asfw-widget').startVerification(); });
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verifying');
  await page.clock.fastForward(10001);
  assert.equal(await page.evaluate(() => window.verification), false);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'error');
  await held?.abort();
  await page.unroute('**/challenge');
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
});

for (const providerEvent of ['forminator:form:submit:complete', 'wpdiscuz_comment_post_complete', 'wpdiscuz_comment_post_failed']) {
  test(`${providerEvent} handles real jQuery events for only the submitted form`, async t => {
    const page = await pageFor(t);
    await page.evaluate(() => {
      const second = document.createElement('form');
      second.innerHTML = '<asfw-widget name="second" challengeurl="/challenge"></asfw-widget>';
      document.body.append(second);
    });
    await page.locator('asfw-widget').evaluateAll(elements => Promise.all(elements.map(el => el.startVerification())));
    await page.evaluate(providerEvent => {
      const form = document.querySelector('form');
      if (providerEvent.startsWith('wpdiscuz_')) {
        jQuery(document.body).trigger(providerEvent, [jQuery(form), new FormData(form), jQuery(form).find('button[type=submit]')]);
      } else {
        jQuery(form).trigger(providerEvent, [{ success: false }]);
      }
    }, providerEvent);
    await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
    assert.equal(await page.locator('asfw-widget').nth(1).evaluate(el => el.getState()), 'verified');
  });
}

for (const outcome of ['validation error', 'transport failure']) {
  test(`WPForms ${outcome} renews only the completed form after preserving in-flight credentials`, async t => {
    const page = await pageFor(t);
    const counts = await guardRoutes(page);
    await addGuards(page);
    await guardsReady(page);
    let receiveRequest;
    const requestReceived = new Promise(resolve => { receiveRequest = resolve; });
    await page.route('**/wpforms-submit', route => receiveRequest(route));
    await page.evaluate(() => {
      const second = document.createElement('form');
      second.innerHTML = '<asfw-widget name="second" challengeurl="/challenge"></asfw-widget>';
      document.body.append(second);
      const form = document.querySelector('form');
      form.addEventListener('submit', event => {
        event.preventDefault();
        window.wpformsPayload = Object.fromEntries(new FormData(form));
        jQuery.ajax({
          method: 'POST', url: '/wpforms-submit', data: new FormData(form), contentType: false, processData: false,
          // WPForms emits this on the submitted form after its request settles.
          complete: (request, status) => jQuery(form).trigger('wpformsAjaxSubmitCompleted', [request, status]),
        });
      });
    });
    await page.locator('asfw-widget').evaluateAll(elements => Promise.all(elements.map(el => el.startVerification())));
    const secondProof = await page.locator('input[name=second]').inputValue();
    await page.locator('form > button[type=submit]').click();
    await page.waitForFunction(() => !!window.wpformsPayload);
    const pending = await requestReceived;
    const payload = await page.evaluate(() => window.wpformsPayload);
    assert.ok(payload.proof);
    assert.equal(await page.locator('input[name=proof]').inputValue(), payload.proof);
    assert.equal(await page.locator('[name=asfw_submit_delay_token]').inputValue(), payload.asfw_submit_delay_token);
    assert.equal(counts.delay, 1);
    if (outcome === 'validation error') {
      await pending.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: false, data: { errors: { email: 'Please use another address.' } } }) });
    } else {
      await pending.abort('failed');
    }
    await page.waitForFunction(() => document.querySelector('[name=asfw_submit_delay_token]').value === 'delay-2');
    assert.equal(await page.locator('asfw-widget').first().evaluate(el => el.getState()), 'idle');
    assert.equal(await page.locator('input[name=proof]').inputValue(), '');
    assert.equal(await page.locator('input[name=second]').inputValue(), secondProof);
    assert.equal(await page.locator('asfw-widget').nth(1).evaluate(el => el.getState()), 'verified');
    assert.equal(counts.delay, 2);
    assert.equal(await page.locator('asfw-widget').first().evaluate(el => el.startVerification()), true);
  });
}

for (const provider of ['Formidable', 'HTML Forms']) {
  test(`${provider} validation response renews only its form using the native provider event contract`, async t => {
    const page = await pageFor(t);
    const counts = await guardRoutes(page);
    await addGuards(page);
    await guardsReady(page);
    await page.evaluate(() => {
      const second = document.createElement('form');
      second.innerHTML = '<asfw-widget name="second" challengeurl="/challenge"></asfw-widget>';
      document.body.append(second);
    });
    await page.locator('asfw-widget').evaluateAll(elements => Promise.all(elements.map(el => el.startVerification())));
    const secondProof = await page.locator('input[name=second]').inputValue();
    const duringCompletion = await page.evaluate(provider => {
      const form = document.querySelector('form');
      const proof = new FormData(form).get('proof');
      if (provider === 'Formidable') {
        jQuery(document).trigger('frmFormErrors', [form, { errors: { email: 'Use another address.' } }]);
      } else {
        // HTML Forms constructs CustomEvent without bubbles=true.
        form.dispatchEvent(new CustomEvent('hf-submitted'));
      }
      return { proof, after: new FormData(form).get('proof') };
    }, provider);
    assert.ok(duringCompletion.proof);
    assert.equal(duringCompletion.after, duringCompletion.proof);
    await page.waitForFunction(() => document.querySelector('[name=asfw_submit_delay_token]').value === 'delay-2');
    assert.equal(await page.locator('asfw-widget').first().evaluate(el => el.getState()), 'idle');
    assert.equal(await page.locator('input[name=proof]').inputValue(), '');
    assert.equal(await page.locator('input[name=second]').inputValue(), secondProof);
    assert.equal(counts.delay, 2);
  });
}

test('Gravity native and legacy render events coalesce after a submitted form', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page);
  await addGuards(page);
  await guardsReady(page);
  await page.locator('asfw-widget').evaluate(el => el.startVerification());
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.id = 'gform_9';
    form.addEventListener('submit', event => event.preventDefault());
    form.requestSubmit();
    jQuery(document).trigger('gform_post_render', [9, 1]);
    document.dispatchEvent(new CustomEvent('gform/post_render', { detail: { formId: 9 } }));
  });
  await page.waitForFunction(() => document.querySelector('[name=asfw_submit_delay_token]').value === 'delay-2');
  assert.equal(counts.delay, 2);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'idle');
});

test('guard replacement and URL changes discard previous responses', async t => {
  const page = await pageFor(t);
  let held;
  await page.route('**/delay', route => { held = route; });
  const counts = await guardRoutes(page);
  await page.route('**/delay', route => { held = route; });
  await page.route('**/new-delay', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    token_id: 'replacement', signature: 'new', issued_at_ms: Date.now(), delay_ms: 1, expires_at: Math.floor(Date.now() / 1000) + 600,
  }) }));
  await addGuards(page);
  await page.waitForFunction(() => document.querySelector('.asfw-submit-delay-status')?.textContent.includes('Preparing'));
  await page.evaluate(() => {
    const oldStatus = document.querySelector('.asfw-submit-delay-status');
    const nextStatus = oldStatus.cloneNode(false);
    nextStatus.dataset.asfwSubmitDelayTokenUrl = '/new-delay';
    oldStatus.replaceWith(nextStatus);
  });
  await guardsReady(page);
  await held?.fulfill({ status: 503, body: '' });
  assert.equal(await page.locator('[name=asfw_submit_delay_token]').inputValue(), 'replacement');
  assert.equal(await page.locator('.asfw-guard-retry').count(), 1);
  assert.equal(counts.delay, 0);
});

test('wpDiscuz inline native and jQuery clicks verify before serialization and renew after transport failure', async t => {
  const page = await pageFor(t);
  const counts = await guardRoutes(page);
  await addGuards(page, { math: true });
  await guardsReady(page);
  let requests = 0;
  await page.route('**/wpdiscuz', async route => {
    requests += 1;
    await new Promise(resolve => setTimeout(resolve, 40));
    await route.fulfill({ status: requests === 1 ? 503 : 200, contentType: 'application/json', body: JSON.stringify({ success: requests !== 1 }) });
  });
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.className = 'wpd_inline_comm_form';
    const wrapper = document.createElement('div');
    wrapper.className = 'wpd-inline-shortcode';
    wrapper.id = 'wpd-inline-123';
    form.before(wrapper); wrapper.append(form);
    const button = form.querySelector('button[type=submit]');
    button.className = 'wpd-inline-submit wpd_not_clicked';
    form.querySelector('asfw-widget').configure({ auto: 'onsubmit', delay: '80' });
    window.providerSubmissions = [];
    // The vendor's actual delegated click, FormData and AJAX completion order.
    jQuery(document.body).on('click.vendor', '.wpd-inline-submit.wpd_not_clicked', function (event) {
      event.preventDefault();
      const form = this.closest('form');
      this.classList.remove('wpd_not_clicked');
      const data = new FormData(form);
      data.append('action', 'wpdAddInlineComment');
      data.append('inline_form_id', '123');
      window.providerSubmissions.push(Object.fromEntries(data));
      jQuery.ajax({ type: 'POST', url: '/wpdiscuz', data, contentType: false, processData: false }).done(response => {
        this.classList.add('wpd_not_clicked');
        if (response.success) form.reset();
      });
    });
  });
  await page.locator('.wpd-inline-submit').click();
  await page.waitForFunction(() => window.providerSubmissions.length === 1);
  assert.ok(await page.evaluate(() => window.providerSubmissions[0].proof));
  assert.equal(await page.locator('input[name=proof]').inputValue(), await page.evaluate(() => window.providerSubmissions[0].proof));
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
  await guardsReady(page);
  assert.equal(await page.locator('.wpd-inline-submit').evaluate(el => el.classList.contains('wpd_not_clicked')), true);
  await page.evaluate(() => jQuery('.wpd-inline-submit').trigger('click'));
  await page.waitForFunction(() => window.providerSubmissions.length === 2);
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
  await guardsReady(page);
  const submitted = await page.evaluate(() => window.providerSubmissions);
  assert.ok(submitted[1].proof);
  assert.equal(submitted[0].asfw_submit_delay_token, 'delay-1');
  assert.equal(submitted[1].asfw_submit_delay_token, 'delay-2');
  assert.equal(requests, 2);
  assert.deepEqual(counts, { delay: 3, math: 3 });
});

test('wpDiscuz main delegated click honors manual mode and resumes automatic verification once', async t => {
  const page = await pageFor(t);
  await page.evaluate(() => {
    const form = document.querySelector('form');
    form.className = 'wpd_main_comm_form';
    const button = form.querySelector('button[type=submit]');
    button.type = 'button';
    button.className = 'wc_comm_submit wpd_not_clicked';
    window.providerSubmissions = [];
    jQuery(document.body).on('click.vendor', '.wc_comm_submit.wpd_not_clicked', function () {
      window.providerSubmissions.push(Object.fromEntries(new FormData(this.closest('form'))));
    });
  });
  await page.locator('.wc_comm_submit').click();
  assert.equal(await page.evaluate(() => window.providerSubmissions.length), 0);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'error');
  await page.locator('asfw-widget').evaluate(el => el.configure({ auto: 'onsubmit', delay: '80' }));
  await page.evaluate(() => {
    jQuery('.wc_comm_submit').trigger('click');
    jQuery('.wc_comm_submit').trigger('click');
  });
  await page.waitForFunction(() => window.providerSubmissions.length === 1);
  assert.ok(await page.evaluate(() => window.providerSubmissions[0].proof));
});

test('batch configuration starts one verification and initializes renamed metadata', async t => {
  const page = await pageFor(t);
  let challenges = 0;
  await page.route('**/challenge', route => { challenges += 1; return route.continue(); });
  await page.locator('asfw-widget').evaluate(el => el.configure({ auto: 'onload', delay: '10', name: 'renamed' }));
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  assert.equal(challenges, 1);
  assert.ok(await page.locator('[name=renamed_started]').inputValue());
});

for (const configure of [false, true]) {
  test(`data field changes ${configure ? 'through configure' : 'directly'} invalidate and rename verification`, async t => {
    const page = await pageFor(t);
    await page.locator('asfw-widget').evaluate(el => el.configure({ name: false, 'data-asfw-field': 'first' }));
    await page.locator('asfw-widget').evaluate(el => el.startVerification());
    await page.locator('asfw-widget').evaluate((el, configure) => {
      if (configure) el.configure({ 'data-asfw-field': 'second' });
      else el.setAttribute('data-asfw-field', 'second');
    }, configure);
    const after = await page.locator('asfw-widget').evaluate(el => {
      const values = new FormData(el.closest('form'));
      return { state: el.getState(), field: el.querySelector('input').name,
        oldProof: values.has('first'), proof: values.get('second') };
    });
    assert.deepEqual(after, { state: 'idle', field: 'second', oldProof: false, proof: '' });
    await page.waitForFunction(() => !!document.querySelector('input[name=second_started]'));
    assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
    assert.equal(await page.evaluate(() => !!new FormData(document.querySelector('form')).get('second')), true);
  });
}

test('lazy loading defers requests while eager loading only prefetches manual verification', async t => {
  const page = await pageFor(t);
  let challenges = 0;
  await page.route('**/challenge', route => { challenges += 1; return route.continue(); });
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '1' }));
  await page.locator('input[name=email]').focus();
  assert.equal(challenges, 0);
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '0' }));
  await page.waitForFunction(() => !!document.querySelector('asfw-widget')._challenge);
  assert.equal(challenges, 1);
  assert.deepEqual(await page.locator('asfw-widget').evaluate(el => ({ state: el.getState(), proof: el.querySelector('input').value })), { state: 'idle', proof: '' });
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
  assert.equal(challenges, 1);
});

test('eager loading shares a pending request with verification and does not retry a failed prefetch automatically', async t => {
  const page = await pageFor(t);
  let receiveRequest;
  let challenges = 0;
  const received = new Promise(resolve => { receiveRequest = resolve; });
  await page.route('**/challenge', route => { challenges += 1; receiveRequest(route); });
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '0' }));
  const request = await received;
  await page.locator('asfw-widget').evaluate(el => { void el.startVerification(); });
  await request.fulfill({ status: 503, body: '' });
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'error');
  await page.locator('asfw-widget').evaluate(el => el.configure({ appearance: 'dark' }));
  assert.equal(challenges, 1);
  await page.unroute('**/challenge');
  await page.route('**/challenge', route => { challenges += 1; return route.continue(); });
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
  assert.equal(challenges, 2);
});

test('eager loading discards a stale response after reset', async t => {
  const page = await pageFor(t);
  let receiveRequest;
  let challenges = 0;
  const received = new Promise(resolve => { receiveRequest = resolve; });
  await page.route('**/challenge', route => {
    challenges += 1;
    if (challenges === 1) receiveRequest(route);
    else return route.continue();
  });
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '0' }));
  const request = await received;
  await page.locator('asfw-widget').evaluate(el => el.reset());
  await request.continue().catch(() => {});
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
  assert.equal(challenges, 2);
});

test('eager loading refreshes a prefetched challenge that expires before manual verification', async t => {
  const page = await pageFor(t);
  await page.clock.install();
  let challenges = 0;
  await page.route('**/challenge', route => {
    challenges += 1;
    if (challenges !== 1) return route.continue();
    const salt = `short?expires=${Math.floor(Date.now() / 1000) + 1}`;
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ algorithm: 'SHA-256', salt,
      challenge: createHash('sha256').update(salt + '2').digest('hex'), maxnumber: 10, signature: 'fixture' }) });
  });
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '0' }));
  await page.waitForFunction(() => !!document.querySelector('asfw-widget')._challenge);
  await page.clock.fastForward(2000);
  await page.locator('asfw-widget .asfw-control').click();
  await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'verified');
  assert.equal(challenges, 2);
});

test('eager loading bounds a stalled prefetch and permits explicit retry', async t => {
  const page = await pageFor(t);
  await page.clock.install();
  let receiveRequest;
  let challenges = 0;
  const received = new Promise(resolve => { receiveRequest = resolve; });
  await page.route('**/challenge', route => {
    challenges += 1;
    if (challenges === 1) receiveRequest(route);
    else return route.continue();
  });
  await page.locator('asfw-widget').evaluate(el => el.configure({ 'data-asfw-lazy': '0' }));
  const request = await received;
  await page.clock.fastForward(10001);
  await page.waitForFunction(() => !document.querySelector('asfw-widget')._challengePromise);
  await request.abort().catch(() => {});
  await page.locator('asfw-widget').evaluate(el => el.configure({ appearance: 'dark' }));
  assert.equal(challenges, 1);
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.getState()), 'idle');
  assert.equal(await page.locator('asfw-widget').evaluate(el => el.startVerification()), true);
  assert.equal(challenges, 2);
});
