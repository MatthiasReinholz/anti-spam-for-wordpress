const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const { chromium, firefox, webkit } = require('playwright');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const browserType = { chromium, firefox, webkit }[process.env.ASFW_BROWSER || 'chromium'];
assert.ok(browserType);

let browser, server, origin, issued = 0;
const markup = `<input type="hidden" name="asfw_checkout_started" value="1"><input type="hidden" name="asfw_checkout_context" value="woocommerce:checkout"><input type="hidden" name="asfw_checkout_context_sig" value="signed-context"><input name="asfw_checkout_website" value=""><asfw-widget name="asfw_checkout" auto="onfocus" challengeurl="/challenge"></asfw-widget>`;

// These are the documented Woo interfaces; the widget/guard runtime is real.
const bootstrap = `
window.checkoutFixture = { values: {}, observers: {}, cleanups: [], observerDispatches: 0, inObserver: false };
window.ASFW_RUNTIME = { honeypotEnabled: true };
window.ASFW_CHECKOUT = ${JSON.stringify({ blocks: true, namespace: 'anti-spam-for-wordpress', field: 'asfw_checkout', fields: ['asfw_checkout', 'asfw_checkout_started', 'asfw_checkout_context', 'asfw_checkout_context_sig', 'asfw_checkout_website'], markup, label: 'Spam protection', message: 'Complete verification' })};
const fixture = window.checkoutFixture;
const effects = [];
const Slot = function () {};
const store = {};
const mount = (node, context) => {
  if (node.type === Slot) return mount(node.props.children[0], 'woocommerce/checkout');
  if (typeof node.type === 'function') return mount(node.type({ ...node.props, context }), context);
  if (!node.type) return null;
  const element = document.createElement(node.type);
  if (node.props.dangerouslySetInnerHTML) element.innerHTML = node.props.dangerouslySetInnerHTML.__html;
  if (node.props.ref) node.props.ref.current = element;
  if (node.props.onSubmit) element.addEventListener('submit', node.props.onSubmit);
  if (node.props.className) element.className = node.props.className;
  document.body.append(element);
  return element;
};
window.wp = {
  element: {
    createElement: (type, props, ...children) => ({ type, props: { ...props, children } }),
    useRef: () => ({ current: null }),
    useEffect: callback => effects.push(callback),
  },
  data: { useSelect: callback => callback(() => ({ getCustomerId: () => 0 })), dispatch: () => ({ setExtensionData(namespace, values, replace) {
    if (fixture.inObserver) fixture.observerDispatches++;
    fixture.values[namespace] = replace ? values : { ...fixture.values[namespace], ...values };
  } }) },
  plugins: { registerPlugin(_name, plugin) {
    mount(plugin.render());
    effects.splice(0).forEach(callback => fixture.cleanups.push(callback()));
  } },
};
const subscribe = kind => callback => { fixture.observers[kind] = callback; return () => delete fixture.observers[kind]; };
window.wc = { blocksCheckout: { ExperimentalOrderMeta: Slot }, wcBlocksData: { checkoutStore: store },
  blocksCheckoutEvents: { checkoutEvents: { onCheckoutValidation: subscribe('validate'), onCheckoutFail: subscribe('fail') } } };
fixture.emit = kind => { fixture.inObserver = true; try { return fixture.observers[kind](); } finally { fixture.inObserver = false; } };
`;

before(async () => {
  server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/challenge') {
      const salt = `checkout-${++issued}?expires=${Math.floor(Date.now() / 1000) + 60}`;
      res.setHeader('Content-Type', 'application/json');
      res.end(JSON.stringify({ algorithm: 'SHA-256', salt, maxnumber: 10, challenge: createHash('sha256').update(salt + '2').digest('hex'), signature: 'fixture' }));
      return;
    }
    if (url.pathname === '/bootstrap.js') {
      res.setHeader('Content-Type', 'text/javascript');
      const manual = url.searchParams.has('manual') ? `window.ASFW_CHECKOUT.markup = window.ASFW_CHECKOUT.markup.replace(${JSON.stringify(' auto="onfocus"')}, '');` : '';
      // The PHP renderer forces onsubmit when an integration selects invisible presentation.
      const invisible = url.searchParams.has('invisible') ? `window.ASFW_CHECKOUT.markup = window.ASFW_CHECKOUT.markup.replace(${JSON.stringify('auto="onfocus"')}, 'auto="onsubmit" presentation="invisible" hidden');` : '';
      const native = url.searchParams.has('native') ? 'window.ASFW_CHECKOUT.blocks = false;' : '';
      const incompatible = url.searchParams.has('incompatible') ? `delete window.wc.blocksCheckoutEvents; window.ASFW_CHECKOUT.compatibilityMessage = 'Checkout verification could not load. Please contact the store.';` : '';
      res.end(bootstrap + manual + invisible + native + incompatible); return;
    }
    if (url.pathname === '/jquery.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(fs.readFileSync(require.resolve('jquery/dist/jquery.min.js'))); return; }
    if (url.pathname === '/checkout.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(fs.readFileSync(path.join(__dirname, '../../assets/js/checkout.js'))); return; }
    if (url.pathname.startsWith('/public/')) {
      const filename = path.join(__dirname, '../../public', path.basename(url.pathname));
      res.setHeader('Content-Type', filename.endsWith('.css') ? 'text/css' : 'text/javascript');
      res.end(fs.readFileSync(filename)); return;
    }
    res.setHeader('Content-Type', 'text/html');
    res.end(`<!doctype html><html><body><script src="/bootstrap.js${url.search}"></script><script src="/jquery.js"></script><script src="/public/script.js"></script><script type="module" src="/public/asfw-widget.js"></script><script src="/checkout.js"></script></body></html>`);
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
  browser = await browserType.launch();
});
after(async () => { await browser?.close(); await new Promise(resolve => server.close(resolve)); });
async function pageFor(t, suffix = '') {
  const page = await browser.newPage();
  t.after(() => page.close());
  await page.goto(origin + suffix);
  await page.waitForFunction(() => window.checkoutFixture?.observers.validate);
  return page;
}

test('Blocks exposes an actionable compatibility fault instead of silently omitting verification', async t => {
  const page = await browser.newPage();
  t.after(() => page.close());
  await page.goto(origin + '/?incompatible=1');
  assert.equal(await page.locator('[role="alert"]').textContent(), 'Checkout verification could not load. Please contact the store.');
  assert.equal(await page.locator('asfw-widget').count(), 0);
});

test('Blocks prepares and synchronizes the real widget before checkout validation', async t => {
  const page = await pageFor(t);
  await page.waitForFunction(() => window.checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
  const result = await page.evaluate(() => ({ result: checkoutFixture.emit('validate'), values: checkoutFixture.values['anti-spam-for-wordpress'], mutations: checkoutFixture.observerDispatches }));
  assert.equal(result.result, true);
  assert.equal(result.values.asfw_checkout_context, 'woocommerce:checkout');
  assert.equal(result.values.asfw_checkout_context_sig, 'signed-context');
  assert.equal(result.values.asfw_checkout_website, '');
  assert.equal(typeof result.values.asfw_checkout, 'string');
  assert.equal(result.mutations, 0);
});

test('Blocks rejects incomplete verification without changing checkout data inside its observer', async t => {
  const page = await pageFor(t);
  await page.waitForFunction(() => document.querySelector('asfw-widget')?.getState() === 'verified');
  await page.evaluate(() => document.querySelector('asfw-widget').reset());
  const result = await page.evaluate(() => ({ result: checkoutFixture.emit('validate'), mutations: checkoutFixture.observerDispatches, payload: checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout }));
  assert.equal(result.result.type, 'error');
  assert.equal(result.payload, '');
  assert.equal(result.mutations, 0);
});

test('Blocks payment failure renews consumed proof outside the Woo event observer', async t => {
  const page = await pageFor(t);
  await page.waitForFunction(() => window.checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
  const first = await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout);
  await page.evaluate(() => checkoutFixture.emit('fail'));
  await page.waitForFunction(previous => {
    const proof = window.checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout;
    return proof && proof !== previous;
  }, first);
  assert.equal(await page.evaluate(() => checkoutFixture.emit('validate')), true);
  assert.equal(await page.evaluate(() => checkoutFixture.observerDispatches), 0);
});

test('Blocks respects manual verification until the visitor activates the control', async t => {
  const page = await pageFor(t, '?manual=1');
  await page.waitForFunction(() => document.querySelector('asfw-widget')?.getState() === 'idle');
  assert.equal(await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout), '');
  assert.equal(await page.evaluate(() => checkoutFixture.emit('validate').type), 'error');
  await page.locator('asfw-widget').locator('button.asfw-control').click();
  await page.waitForFunction(() => window.checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
  assert.equal(await page.evaluate(() => checkoutFixture.emit('validate')), true);
});

for (const mode of ['manual', 'automatic', 'invisible']) {
  test(`Blocks ${mode} protection renews complete transport after a failed checkout`, async t => {
    const page = await pageFor(t, mode === 'manual' ? '?manual=1' : mode === 'invisible' ? '?invisible=1' : '');
    const widget = page.locator('asfw-widget');
    if (mode === 'manual') {
      assert.equal(await page.evaluate(() => checkoutFixture.emit('validate').type), 'error');
      await widget.locator('button.asfw-control').click();
    }
    await page.waitForFunction(() => checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
    const first = await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout);
    assert.equal(await page.evaluate(() => checkoutFixture.emit('validate')), true);
    assert.equal(await widget.isVisible(), mode !== 'invisible');
    await page.evaluate(() => checkoutFixture.emit('fail'));
    if (mode === 'manual') {
      await page.waitForFunction(() => document.querySelector('asfw-widget').getState() === 'idle');
      assert.equal(await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout), '');
      assert.equal(await page.evaluate(() => checkoutFixture.emit('validate').type), 'error');
      await widget.locator('button.asfw-control').click();
    }
    await page.waitForFunction(previous => {
      const proof = checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout;
      return proof && proof !== previous;
    }, first);
    const result = await page.evaluate(() => ({ values: checkoutFixture.values['anti-spam-for-wordpress'], validation: checkoutFixture.emit('validate'), mutations: checkoutFixture.observerDispatches }));
    assert.equal(result.validation, true);
    assert.equal(result.values.asfw_checkout_context, 'woocommerce:checkout');
    assert.equal(result.values.asfw_checkout_context_sig, 'signed-context');
    assert.equal(result.values.asfw_checkout_website, '');
    assert.ok(Number(result.values.asfw_checkout_started) > 0);
    assert.equal(result.mutations, 0);
    assert.equal(await widget.isVisible(), mode !== 'invisible');
  });
}

test('Blocks invisible challenge failure exposes accessible retry and recovers without a card', async t => {
  const page = await browser.newPage();
  t.after(() => page.close());
  let challenges = 0;
  await page.route('**/challenge', route => ++challenges === 1
    ? route.fulfill({ status: 503, body: '' }) : route.continue());
  await page.goto(origin + '/?invisible=1');
  await page.waitForFunction(() => document.querySelector('asfw-widget')?.getState() === 'error');
  const widget = page.locator('asfw-widget');
  assert.equal(await widget.isVisible(), true);
  assert.equal(await widget.locator('.asfw-widget').isVisible(), false);
  assert.equal(await page.evaluate(() => checkoutFixture.emit('validate').type), 'error');
  assert.equal(await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout), '');
  const retry = widget.locator('button.asfw-verification-retry');
  await retry.click();
  await page.waitForFunction(() => checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
  assert.equal(challenges, 2);
  assert.equal(await widget.isVisible(), false);
  assert.equal(await page.evaluate(() => checkoutFixture.emit('validate')), true);
  assert.equal(await page.evaluate(() => checkoutFixture.observerDispatches), 0);
});

for (const surface of ['classic checkout', 'account registration']) {
  for (const mode of ['manual', 'automatic', 'invisible']) {
    test(`${surface} ${mode} native submission preserves proof and submitter across retry`, async t => {
      const page = await browser.newPage();
      t.after(() => page.close());
      await page.goto(origin + '/?native=1');
      await page.waitForFunction(() => customElements.get('asfw-widget'));
      const checkout = surface === 'classic checkout';
      const field = checkout ? 'asfw_checkout' : 'asfw_register';
      const context = checkout ? 'woocommerce:checkout' : 'woocommerce:register';
      const submitName = checkout ? 'woocommerce_checkout_place_order' : 'register';
      await page.evaluate(({ field, context, submitName, checkout, mode }) => {
        const form = document.createElement('form');
        form.id = 'native-commerce';
        const automatic = mode === 'manual' ? '' : mode === 'automatic' ? 'auto="onload"' : 'auto="onsubmit"';
        const presentation = mode === 'invisible' ? 'presentation="invisible" hidden' : '';
        form.innerHTML = `<input name="email" type="email" required>
          <div ${checkout ? 'data-asfw-checkout-classic' : ''}>
            <asfw-widget name="${field}" ${automatic} ${presentation} challengeurl="/challenge"></asfw-widget>
            <input type="hidden" name="${field}_started" value="">
            <input type="hidden" name="${field}_context" value="${context}">
            <input type="hidden" name="${field}_context_sig" value="signed-context">
            <input type="hidden" name="${field}_website" value="">
          </div><button type="submit" name="${submitName}" value="submit-order">Submit</button>`;
        window.nativeCommerceTemplate = form.innerHTML;
        window.nativeCommerceSubmissions = [];
        form.addEventListener('submit', event => {
          event.preventDefault();
          window.nativeCommerceSubmissions.push({ fields: Object.fromEntries(new FormData(form)), submitter: event.submitter?.name, value: event.submitter?.value });
        });
        document.body.append(form);
      }, { field, context, submitName, checkout, mode });
      const form = page.locator('#native-commerce');
      const widget = form.locator('asfw-widget');
      await page.waitForFunction(state => document.querySelector('#native-commerce asfw-widget')?.getState() === state,
        mode === 'automatic' ? 'verified' : 'idle');
      await form.locator('[name=email]').fill('customer@example.test');
      if (mode === 'manual') {
        await widget.locator('button.asfw-control').click();
        await page.waitForFunction(() => document.querySelector('#native-commerce asfw-widget').getState() === 'verified');
      }
      await form.locator('button[type=submit]').last().click();
      await page.waitForFunction(() => nativeCommerceSubmissions.length === 1);
      const first = await page.evaluate(() => nativeCommerceSubmissions[0]);
      assert.ok(first.fields[field]);
      assert.equal(JSON.parse(Buffer.from(first.fields[field], 'base64')).number, '2');
      assert.equal(first.fields[field + '_context'], context);
      assert.equal(first.fields[field + '_context_sig'], 'signed-context');
      assert.ok(Number(first.fields[field + '_started']) > 0);
      assert.equal(first.fields[field + '_website'], '');
      assert.equal(first.fields.email, 'customer@example.test');
      assert.equal(first.submitter, submitName);
      assert.equal(first.value, 'submit-order');
      assert.equal(await widget.isVisible(), mode !== 'invisible');

      if (checkout) {
        await page.evaluate(() => jQuery(document.body).trigger('checkout_error'));
      } else {
        // Native account signup returns a fresh document on validation failure.
        // Replacing its form exercises fresh widget discovery without an AJAX adapter.
        await form.evaluate(node => {
          const fresh = document.createElement('form');
          fresh.id = node.id;
          fresh.innerHTML = nativeCommerceTemplate;
          fresh.querySelector('[name=email]').value = node.querySelector('[name=email]').value;
          node.replaceWith(fresh);
        });
        await page.evaluate(() => document.querySelector('#native-commerce').addEventListener('submit', event => {
          event.preventDefault();
          nativeCommerceSubmissions.push({ fields: Object.fromEntries(new FormData(event.target)), submitter: event.submitter?.name });
        }));
      }
      await page.waitForFunction(({ state, field, previous }) => {
        const node = document.querySelector('#native-commerce');
        return node.querySelector('asfw-widget').getState() === state &&
          (state !== 'verified' || new FormData(node).get(field) !== previous);
      }, { state: mode === 'automatic' ? 'verified' : 'idle', field, previous: first.fields[field] });
      const renewed = await form.evaluate((node, name) => new FormData(node).get(name), field);
      if (mode === 'automatic') {
        assert.ok(renewed);
        assert.notEqual(renewed, first.fields[field]);
      } else {
        assert.equal(renewed, '');
      }
      assert.equal(await form.locator('[name=email]').inputValue(), 'customer@example.test');
      if (mode === 'manual') {
        await widget.locator('button.asfw-control').click();
        await page.waitForFunction(() => document.querySelector('#native-commerce asfw-widget').getState() === 'verified');
      }
      await form.locator('button[type=submit]').last().click();
      await page.waitForFunction(() => nativeCommerceSubmissions.length === 2);
      const second = await page.evaluate(() => nativeCommerceSubmissions[1]);
      assert.ok(second.fields[field]);
      assert.notEqual(second.fields[field], first.fields[field]);
      assert.equal(second.fields[field + '_context'], context);
      assert.equal(second.submitter, submitName);
      assert.equal(await widget.isVisible(), mode !== 'invisible');
    });
  }
}

test('classic checkout errors renew only the classic form, preserving another protected form', async t => {
  const page = await pageFor(t);
  await page.waitForFunction(() => window.checkoutFixture.values['anti-spam-for-wordpress']?.asfw_checkout);
  const blockProof = await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout);
  await page.evaluate(html => {
    const form = document.createElement('form');
    form.id = 'classic';
    form.innerHTML = '<div data-asfw-checkout-classic>' + html.replace('auto="onfocus"', 'auto="onload"') + '</div>';
    document.body.append(form);
  }, markup);
  await page.waitForFunction(() => document.querySelector('#classic asfw-widget')?.getState() === 'verified');
  const classicProof = await page.locator('#classic').evaluate(form => new FormData(form).get('asfw_checkout'));
  await page.evaluate(() => window.jQuery(document.body).trigger('checkout_error'));
  await page.waitForFunction(previous => {
    const proof = new FormData(document.querySelector('#classic')).get('asfw_checkout');
    return proof && proof !== previous;
  }, classicProof);
  assert.equal(await page.evaluate(() => checkoutFixture.values['anti-spam-for-wordpress'].asfw_checkout), blockProof);
});
