const ASFW_WIDGET_STYLE_URL = new URL('./asfw-widget-internal.css', import.meta.url);
ASFW_WIDGET_STYLE_URL.search = new URL(import.meta.url).search;

const ASFW_DEFAULT_STRINGS = {
  error: 'Verification failed. Try again later.',
  footer: 'Protected by Anti Spam for WordPress',
  intro: 'This check helps prevent spam.',
  label: "I'm not a robot",
  privacy: 'Privacy',
  required: 'Please verify before submitting.',
  retry: 'Try again',
  verified: 'Verified',
  verifying: 'Verifying...',
  waitAlert: 'Verifying... please wait.',
};

const ASFW_TEXT_ENCODER = new TextEncoder();
const ASFW_FORM_SUBMISSIONS = new WeakMap();
const ASFW_REQUEST_TIMEOUT_MS = 10000;

function asfwSleep(ms, signal) {
  return new Promise((resolve, reject) => {
    const cancel = () => {
      window.clearTimeout(timer);
      reject(new DOMException('Verification canceled.', 'AbortError'));
    };
    const timer = window.setTimeout(() => {
      signal?.removeEventListener('abort', cancel);
      resolve();
    }, ms);
    signal?.addEventListener('abort', cancel, { once: true });
    if (signal?.aborted) cancel();
  });
}

async function asfwSha256Hex(value) {
  const digest = await crypto.subtle.digest('SHA-256', ASFW_TEXT_ENCODER.encode(value));
  return [...new Uint8Array(digest)]
    .map((byte) => byte.toString(16).padStart(2, '0'))
    .join('');
}

function asfwBase64Encode(value) {
  return window.btoa(value);
}

function asfwSetOptionalDataAttribute(element, name, value) {
  if (value) {
    element.dataset[name] = value;
    return;
  }

  element.removeAttribute(`data-${name}`);
}

class ASFWWidgetElement extends HTMLElement {
  static get observedAttributes() {
    return ['appearance', 'auto', 'challengeurl', 'data-asfw-challengeurl', 'data-asfw-field', 'data-asfw-lazy', 'data-asfw-min-submit-time', 'data-asfw-privacy-new-tab', 'data-asfw-privacy-url', 'delay', 'floating', 'hidefooter', 'hidelogo', 'layout', 'name', 'strings'];
  }

  constructor() {
    super();
    this._challenge = null;
    this._challengeIssuedAt = 0;
    this._challengeUrl = '';
    this._challengePromise = null;
    this._challengeController = null;
    this._prefetchStarted = false;
    this._form = null;
    this._verifyPromise = null;
    this._verificationGeneration = 0;
    this._controller = null;
    this._autoStarted = false;
    this._state = 'idle';

    this._boundClick = this.handleClick.bind(this);
    this._boundSubmit = this.handleSubmit.bind(this);
    this._boundInteract = this.handleInteractiveTrigger.bind(this);
    this._boundRenew = () => {
      this.reset();
      this.refresh();
    };
  }

  connectedCallback() {
    if (!this._rendered) {
      this.render();
      this._rendered = true;
    }

    this.attachFormListeners();
    this.refresh();
  }

  disconnectedCallback() {
    this.clearVerification(true);
    this.detachFormListeners();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (this._configuring) {
      if (oldValue !== newValue && ['name', 'data-asfw-field', 'delay', 'data-asfw-min-submit-time'].includes(name)) this._configurationChanged = true;
      return;
    }
    if (!this.isConnected || !this._rendered) {
      return;
    }

    if (oldValue !== newValue && ['name', 'data-asfw-field', 'delay', 'data-asfw-min-submit-time'].includes(name)) {
      this.clearVerification(true);
    }
    this.refresh();
  }

  configure(attrs = {}) {
    this._configuring = true;
    try {
      Object.entries(attrs).forEach(([key, value]) => {
        if (key === 'strings' && typeof value === 'object' && value !== null) {
          this.setAttribute('strings', JSON.stringify(value));
          return;
        }
        if (value === false || value === null || value === undefined || value === '') {
          this.removeAttribute(key);
          return;
        }
        this.setAttribute(key, String(value));
      });
    } finally {
      this._configuring = false;
      if (this._rendered && this.isConnected) {
        if (this._configurationChanged) this.clearVerification(true);
        this.refresh();
      }
      this._configurationChanged = false;
    }
  }

  getState() {
    return this._state;
  }

  reset() {
    this.clearVerification(true);
  }

  render() {
    const root = this.attachShadow({ mode: 'open' });
    const content = document.createElement('template');
    content.innerHTML = `
      <div class="asfw-widget-shell" data-state="idle" hidden>
        <div class="asfw-widget">
          <div class="asfw-intro" hidden></div>
          <div class="asfw-main">
            <button type="button" class="asfw-control">
              <span class="asfw-indicator" aria-hidden="true">
                <svg class="asfw-check" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                  <path fill="currentColor" d="M9.55 18 4.8 13.2l1.4-1.4 3.35 3.35 8.25-8.25 1.4 1.4Z"></path>
                </svg>
                <span class="asfw-spinner"></span>
              </span>
              <span class="asfw-labels">
                <span class="asfw-label"></span>
                <span class="asfw-status" aria-live="polite"></span>
              </span>
            </button>
          </div>
          <div class="asfw-error" aria-live="polite"></div>
          <div class="asfw-footer">
            <svg class="asfw-footer-icon" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path fill="currentColor" d="M17 9h-1V7a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-6 0V7a2 2 0 1 1 4 0v2h-4Z"></path>
            </svg>
            <span class="asfw-footer-text"></span>
            <a class="asfw-footer-link" rel="noopener noreferrer"></a>
          </div>
        </div>
      </div>
    `;

    root.appendChild(content.content);

    this._shell = root.querySelector('.asfw-widget-shell');
    this._button = root.querySelector('.asfw-control');
    this._intro = root.querySelector('.asfw-intro');
    this._label = root.querySelector('.asfw-label');
    this._status = root.querySelector('.asfw-status');
    this._error = root.querySelector('.asfw-error');
    this._footer = root.querySelector('.asfw-footer');
    this._footerIcon = root.querySelector('.asfw-footer-icon');
    this._footerLink = root.querySelector('.asfw-footer-link');
    this._footerText = root.querySelector('.asfw-footer-text');
    // Keep native submission, FormData and integration serializers working.
    this._valueInput = document.createElement('input');
    this._valueInput.type = 'hidden';
    this._valueInput.className = 'asfw-hidden-value';
    this.appendChild(this._valueInput);

    this._button.addEventListener('click', this._boundClick);
    this._styleFallback = document.createElement('div');
    this._styleFallback.hidden = true;
    this._styleError = document.createElement('p');
    this._styleError.setAttribute('role', 'alert');
    this._styleRetry = document.createElement('button');
    this._styleRetry.type = 'button';
    this._styleRetry.addEventListener('click', () => {
      this.clearVerification(true);
      this._autoStarted = false;
      this.loadStyles();
      this.refresh();
    });
    this._styleFallback.append(this._styleError, this._styleRetry);
    root.appendChild(this._styleFallback);
    this.loadStyles();
  }

  loadStyles() {
    this._stylesheet?.remove();
    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = ASFW_WIDGET_STYLE_URL.href;
    this._stylesheet = stylesheet;
    this._styleRetry.disabled = true;
    this._stylesReady = new Promise(resolve => {
      const finish = loaded => {
        window.clearTimeout(timeout);
        stylesheet.removeEventListener('load', onLoad);
        stylesheet.removeEventListener('error', onError);
        this._shell.hidden = !loaded;
        this._styleFallback.hidden = loaded;
        this._styleRetry.disabled = false;
        if (!loaded) stylesheet.remove();
        resolve(loaded);
      };
      const onLoad = () => finish(true);
      const onError = () => finish(false);
      const timeout = window.setTimeout(onError, 10000);
      stylesheet.addEventListener('load', onLoad);
      stylesheet.addEventListener('error', onError);
      this.shadowRoot.prepend(stylesheet);
    });
  }

  refresh() {
    const challengeUrl = this.getChallengeUrl();
    if (challengeUrl !== this._challengeUrl) {
      this._challengeUrl = challengeUrl;
      this.clearVerification(true);
    }

    const strings = this.getStrings();
    this._styleError.textContent = strings.error;
    this._styleRetry.textContent = strings.retry;
    const privacyUrl = this.getPrivacyUrl();
    const privacyNewTab = this.opensPrivacyInNewTab();
    this._label.textContent = strings.label;
    this._intro.textContent = strings.intro;
    this._intro.hidden = this.getLayout() !== 'extended';
    this._footerText.textContent = strings.footer;
    this._footer.hidden = this.hasAttribute('hidefooter');
    this._footerIcon.toggleAttribute('hidden', this.hasAttribute('hidelogo'));
    this._footerLink.hidden = privacyUrl === '';
    this._footerLink.textContent = strings.privacy;
    this._footerLink.href = privacyUrl || '#';
    this._footerLink.target = privacyNewTab ? '_blank' : '_self';
    this._valueInput.name = this.getFieldName();
    this._shell.dataset.appearance = this.getAppearance();
    this._shell.dataset.layout = this.getLayout();
    asfwSetOptionalDataAttribute(this._shell, 'floating', this.getAttribute('floating') || '');

    if (this._state === 'idle') {
      this.setState('idle');
    } else if (this._state === 'verified') {
      this.setState('verified');
    } else if (this._state === 'verifying') {
      this.setState('verifying');
    } else if (this._state === 'error') {
      this.setState('error', this._error.textContent);
    }

    if (this.getAutoMode() === 'onload' && !this._autoStarted) {
      this._autoStarted = true;
      void this.startVerification();
    } else if (this.getAutoMode() !== 'onload' && this.getAttribute('data-asfw-lazy') === '0' && !this._prefetchStarted) {
      // Eager loading prepares data without solving or marking a manual widget
      // verified. A failed prefetch is retried only on explicit verification.
      this._prefetchStarted = true;
      void this.ensureChallenge(this._verificationGeneration).catch(() => {});
    }
  }

  attachFormListeners() {
    const form = this.closest('form');
    if (form === this._form) {
      return;
    }

    this.detachFormListeners();
    this._form = form;

    if (!this._form) {
      return;
    }

    this._form.addEventListener('submit', this._boundSubmit, true);
    this._form.addEventListener('focusin', this._boundInteract, true);
    this._form.addEventListener('pointerdown', this._boundInteract, true);
    this._form.addEventListener('keydown', this._boundInteract, true);
    this._form.addEventListener('asfw:renew', this._boundRenew);
  }

  detachFormListeners() {
    if (!this._form) {
      return;
    }

    this._form.removeEventListener('submit', this._boundSubmit, true);
    this._form.removeEventListener('focusin', this._boundInteract, true);
    this._form.removeEventListener('pointerdown', this._boundInteract, true);
    this._form.removeEventListener('keydown', this._boundInteract, true);
    this._form.removeEventListener('asfw:renew', this._boundRenew);
    ASFW_FORM_SUBMISSIONS.delete(this._form);
    this._form = null;
  }

  getStrings() {
    const raw = this.getAttribute('strings');
    if (!raw) {
      return { ...ASFW_DEFAULT_STRINGS };
    }

    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const strings = { ...ASFW_DEFAULT_STRINGS };
        for (const key of Object.keys(strings)) {
          if (Object.hasOwn(parsed, key) && typeof parsed[key] === 'string' && parsed[key].trim() !== '') {
            strings[key] = parsed[key];
          }
        }
        return strings;
      }
    } catch (error) {
      console.warn('ASFW widget strings could not be parsed.', error);
    }

    return { ...ASFW_DEFAULT_STRINGS };
  }

  getAppearance() {
    return this.getAttribute('appearance') === 'dark' ? 'dark' : 'light';
  }

  getLayout() {
    return this.getAttribute('layout') === 'extended' ? 'extended' : 'compact';
  }

  getFieldName() {
    return this.getAttribute('name') || this.dataset.asfwField || 'asfw';
  }

  getAutoMode() {
    const value = this.getAttribute('auto') || '';
    return ['onload', 'onfocus', 'onsubmit'].includes(value) ? value : '';
  }

  getChallengeUrl() {
    return this.getAttribute('challengeurl') || this.getAttribute('data-asfw-challengeurl') || '';
  }

  getPrivacyUrl() {
    return this.getAttribute('data-asfw-privacy-url') || '';
  }

  opensPrivacyInNewTab() {
    return this.getAttribute('data-asfw-privacy-new-tab') === '1';
  }

  getDelayMs() {
    const delay = Number.parseInt(this.getAttribute('delay') || '0', 10);
    return Number.isFinite(delay) && delay > 0 ? delay : 0;
  }

  getMinSubmitTimeMs() {
    const seconds = Number.parseInt(this.getAttribute('data-asfw-min-submit-time') || '0', 10);
    return Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : 0;
  }

  getExpiryTimestamp(challenge) {
    if (!challenge || typeof challenge.salt !== 'string') {
      return null;
    }

    const [, query = ''] = challenge.salt.split('?');
    const params = new URLSearchParams(query);
    const expires = Number.parseInt(params.get('expires') || '', 10);

    return Number.isFinite(expires) && expires > 0 ? expires * 1000 : null;
  }

  isChallengeExpired() {
    if (!this._challenge) {
      return false;
    }

    const expiresAt = this.getExpiryTimestamp(this._challenge);
    return expiresAt !== null && Date.now() >= expiresAt;
  }

  clearVerification(clearChallenge = false) {
    this._verificationGeneration += 1;
    this._controller?.abort();
    this._controller = null;
    this._challengeController?.abort();
    this._challengeController = null;
    this._challengePromise = null;
    this._verifyPromise = null;
    if (this._form) ASFW_FORM_SUBMISSIONS.delete(this._form);
    if (this._valueInput) this._valueInput.value = '';
    if (clearChallenge) {
      this._challenge = null;
      this._challengeIssuedAt = 0;
      this._prefetchStarted = false;
    }
    this._autoStarted = false;
    this.setState('idle');
  }

  setState(state, errorMessage = '') {
    const strings = this.getStrings();

    this._state = state;
    if (!this._shell) return;
    this._shell.dataset.state = state;
    this._button.disabled = state === 'verifying';

    if (state === 'verified') {
      this._status.textContent = strings.verified;
      this._error.textContent = '';
      return;
    }

    if (state === 'verifying') {
      this._status.textContent = strings.verifying;
      this._error.textContent = '';
      return;
    }

    if (state === 'error') {
      this._status.textContent = '';
      this._error.textContent = errorMessage || strings.error;
      return;
    }

    this._status.textContent = '';
    this._error.textContent = '';
  }

  handleClick(event) {
    event.preventDefault();

    if (this._state === 'verified') {
      return;
    }

    void this.startVerification();
  }

  handleInteractiveTrigger(event) {
    if (this.getAutoMode() !== 'onfocus') {
      return;
    }

    if (!this.contains(event.target) && this._state === 'idle') {
      void this.startVerification();
    }
  }

  async handleSubmit(event) {
    const form = this._form;
    if (!form || event.defaultPrevented) return;
    const widgets = [...form.querySelectorAll('asfw-widget')].filter(widget => widget._form === form);
    for (const widget of widgets) {
      if (widget.isChallengeExpired()) widget.clearVerification(true);
    }
    const pending = widgets.filter(widget => widget._state !== 'verified' || !widget._valueInput.value);
    if (!pending.length) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    if (ASFW_FORM_SUBMISSIONS.has(form)) return;
    const manual = pending.find(widget => widget.getAutoMode() !== 'onsubmit');
    if (manual) {
      if (manual._state !== 'verifying') manual.setState('error', manual.getStrings().required);
      manual._button.focus();
      return;
    }

    // One continuation per form, including when it contains several widgets.
    const attempt = { widgets, generations: widgets.map(widget => widget._verificationGeneration) };
    const submitter = event.submitter || null;
    ASFW_FORM_SUBMISSIONS.set(form, attempt);
    try {
      const results = await Promise.all(pending.map(widget => widget.startVerification()));
      if (ASFW_FORM_SUBMISSIONS.get(form) !== attempt || !form.isConnected ||
          widgets.some((widget, index) => widget._form !== form || !widget.isConnected ||
            widget._verificationGeneration !== attempt.generations[index])) return;
      if (results.some(result => !result) || widgets.some(widget => widget.isChallengeExpired())) {
        pending.find(widget => widget._state !== 'verified')?._button.focus();
        return;
      }
      // No bypass flag: the re-entered event must still pass every current guard.
      // requestSubmit can return without a submit event when native validation fails.
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submitter?.form === form ? submitter : undefined);
      } else if (submitter?.form === form && typeof submitter.click === 'function') {
        submitter.click();
      }
    } finally {
      if (ASFW_FORM_SUBMISSIONS.get(form) === attempt) ASFW_FORM_SUBMISSIONS.delete(form);
    }
  }

  async fetchChallenge(signal) {
    const challengeUrl = this.getChallengeUrl();
    if (!challengeUrl) {
      throw new Error('Missing challenge URL.');
    }

    const response = await fetch(challengeUrl, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
      cache: 'no-store',
      signal,
    });

    if (!response.ok) {
      throw new Error(`Challenge request failed with status ${response.status}.`);
    }

    const data = await response.json();
    if (
      !data ||
      data.algorithm !== 'SHA-256' ||
      typeof data.challenge !== 'string' ||
      typeof data.salt !== 'string' ||
      typeof data.signature !== 'string'
    ) {
      throw new Error('Challenge response is invalid.');
    }

    const maxNumber = Number.parseInt(String(data.maxnumber), 10);
    if (!Number.isFinite(maxNumber) || maxNumber <= 0 || maxNumber > 1000000) {
      throw new Error('Challenge difficulty is invalid.');
    }

    return {
      algorithm: data.algorithm,
      challenge: data.challenge,
      maxnumber: maxNumber,
      salt: data.salt,
      signature: data.signature,
    };
  }

  async ensureChallenge(generation) {
    if (this._challenge && !this.isChallengeExpired()) {
      return this._challenge;
    }

    if (this._challengePromise) return this._challengePromise;

    const controller = new AbortController();
    this._challengeController = controller;
    this._challengePromise = (async () => {
      const timeout = window.setTimeout(() => controller.abort(), ASFW_REQUEST_TIMEOUT_MS);
      try {
        const challenge = await this.fetchChallenge(controller.signal);
        if (generation !== this._verificationGeneration) return null;
        this._challenge = challenge;
        this._challengeIssuedAt = Date.now();
        return challenge;
      } finally {
        window.clearTimeout(timeout);
        if (this._challengeController === controller) {
          this._challengePromise = null;
          this._challengeController = null;
        }
      }
    })();
    return this._challengePromise;
  }

  async solveChallenge(challenge, generation, signal) {
    for (let number = 0; number <= challenge.maxnumber; number += 1) {
      if (generation !== this._verificationGeneration) return null;
      if (await asfwSha256Hex(`${challenge.salt}${number}`) === challenge.challenge) {
        return String(number);
      }

      if (number > 0 && number % 250 === 0) {
        await asfwSleep(0, signal);
      }
    }

    return null;
  }

  async startVerification() {
    if (!this.isConnected || !this._rendered) return false;
    if (this._verifyPromise) {
      return this._verifyPromise;
    }

    if (!window.crypto?.subtle) {
      this.setState('error', this.getStrings().error);
      return false;
    }

    const generation = this._verificationGeneration;
    const controller = new AbortController();
    this._controller = controller;
    this._verifyPromise = (async () => {
      try {
        this.setState('verifying');

        if (!await this._stylesReady) {
          throw new Error('Widget stylesheet could not be loaded.');
        }

        if (generation !== this._verificationGeneration) return false;
        const challenge = await this.ensureChallenge(generation);
        if (generation !== this._verificationGeneration) return false;
        const number = await this.solveChallenge(challenge, generation, controller.signal);
        if (generation !== this._verificationGeneration) return false;
        if (number === null) {
          throw new Error('Challenge could not be solved.');
        }

        if (this.getDelayMs() > 0) {
          await asfwSleep(this.getDelayMs(), controller.signal);
        }

        const minSubmitTimeMs = this.getMinSubmitTimeMs();
        if (minSubmitTimeMs > 0 && this._challengeIssuedAt > 0) {
          const remainingMs = minSubmitTimeMs - (Date.now() - this._challengeIssuedAt);
          if (remainingMs > 0) {
            await asfwSleep(remainingMs, controller.signal);
          }
        }

        if (generation !== this._verificationGeneration) return false;
        if (this.isChallengeExpired()) throw new Error('Challenge expired during verification.');
        this._valueInput.value = asfwBase64Encode(JSON.stringify({
          algorithm: challenge.algorithm,
          challenge: challenge.challenge,
          number,
          salt: challenge.salt,
          signature: challenge.signature,
        }));

        this.setState('verified');
        return true;
      } catch (error) {
        if (generation !== this._verificationGeneration) return false;
        console.error('ASFW widget verification failed.', error);
        this._valueInput.value = '';
        this._challenge = null;
        this.setState('error', this.getStrings().error);
        return false;
      } finally {
        if (generation === this._verificationGeneration) {
          this._verifyPromise = null;
          this._controller = null;
        }
      }
    })();

    return this._verifyPromise;
  }
}

if (!customElements.get('asfw-widget')) {
  customElements.define('asfw-widget', ASFWWidgetElement);
}
