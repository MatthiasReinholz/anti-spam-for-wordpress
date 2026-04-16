const ASFW_DEFAULT_STRINGS = {
  error: 'Verification failed. Try again later.',
  footer: 'Protected by Anti Spam for WordPress',
  label: "I'm not a robot",
  required: 'Please verify before submitting.',
  verified: 'Verified',
  verifying: 'Verifying...',
  waitAlert: 'Verifying... please wait.',
};

const ASFW_TEXT_ENCODER = new TextEncoder();

function asfwSleep(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
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

class ASFWWidgetElement extends HTMLElement {
  static get observedAttributes() {
    return ['auto', 'challengeurl', 'data-asfw-challengeurl', 'data-asfw-min-submit-time', 'data-asfw-privacy-new-tab', 'data-asfw-privacy-url', 'delay', 'floating', 'hidefooter', 'hidelogo', 'name', 'strings'];
  }

  constructor() {
    super();
    this._challenge = null;
    this._challengeIssuedAt = 0;
    this._challengeUrl = '';
    this._form = null;
    this._verifyPromise = null;
    this._allowNextSubmit = false;
    this._autoStarted = false;
    this._state = 'idle';

    this._boundClick = this.handleClick.bind(this);
    this._boundSubmit = this.handleSubmit.bind(this);
    this._boundInteract = this.handleInteractiveTrigger.bind(this);
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
    this.detachFormListeners();
  }

  attributeChangedCallback() {
    if (!this.isConnected || !this._rendered) {
      return;
    }

    this.refresh();
  }

  configure(attrs = {}) {
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

    this.refresh();
  }

  getState() {
    return this._state;
  }

  reset() {
    this.clearVerification(true);
  }

  render() {
    this.innerHTML = `
      <div class="asfw-widget-shell" data-state="idle">
        <div class="asfw-widget">
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
        <input type="hidden" class="asfw-hidden-value">
      </div>
    `;

    this._shell = this.querySelector('.asfw-widget-shell');
    this._button = this.querySelector('.asfw-control');
    this._label = this.querySelector('.asfw-label');
    this._status = this.querySelector('.asfw-status');
    this._error = this.querySelector('.asfw-error');
    this._footer = this.querySelector('.asfw-footer');
    this._footerIcon = this.querySelector('.asfw-footer-icon');
    this._footerLink = this.querySelector('.asfw-footer-link');
    this._footerText = this.querySelector('.asfw-footer-text');
    this._valueInput = this.querySelector('.asfw-hidden-value');

    this._button.addEventListener('click', this._boundClick);
  }

  refresh() {
    const challengeUrl = this.getChallengeUrl();
    if (challengeUrl !== this._challengeUrl) {
      this._challengeUrl = challengeUrl;
      this.clearVerification(true);
    }

    const strings = this.getStrings();
    const privacyUrl = this.getPrivacyUrl();
    const privacyNewTab = this.opensPrivacyInNewTab();
    this._label.textContent = strings.label;
    this._footerText.textContent = strings.footer;
    this._footer.hidden = this.hasAttribute('hidefooter');
    this._footerIcon.hidden = this.hasAttribute('hidelogo');
    this._footerLink.hidden = privacyUrl === '';
    this._footerLink.textContent = strings.privacy || 'Privacy';
    this._footerLink.href = privacyUrl || '#';
    this._footerLink.target = privacyNewTab ? '_blank' : '_self';
    this._valueInput.name = this.getFieldName();
    this._shell.dataset.floating = this.getAttribute('floating') || '';

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
  }

  detachFormListeners() {
    if (!this._form) {
      return;
    }

    this._form.removeEventListener('submit', this._boundSubmit, true);
    this._form.removeEventListener('focusin', this._boundInteract, true);
    this._form.removeEventListener('pointerdown', this._boundInteract, true);
    this._form.removeEventListener('keydown', this._boundInteract, true);
    this._form = null;
  }

  getStrings() {
    const raw = this.getAttribute('strings');
    if (!raw) {
      return { ...ASFW_DEFAULT_STRINGS };
    }

    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        return { ...ASFW_DEFAULT_STRINGS, ...parsed };
      }
    } catch (error) {
      console.warn('ASFW widget strings could not be parsed.', error);
    }

    return { ...ASFW_DEFAULT_STRINGS };
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
    this._valueInput.value = '';
    if (clearChallenge) {
      this._challenge = null;
      this._challengeIssuedAt = 0;
    }
    this._autoStarted = this.getAutoMode() === 'onload' && this._autoStarted;
    this.setState('idle');
  }

  setState(state, errorMessage = '') {
    const strings = this.getStrings();

    this._state = state;
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
    if (this._allowNextSubmit) {
      this._allowNextSubmit = false;
      return;
    }

    if (this.isChallengeExpired()) {
      this.clearVerification(true);
    }

    if (this._state === 'verified' && this._valueInput.value !== '') {
      return;
    }

    if (this._state === 'verifying') {
      event.preventDefault();
      event.stopPropagation();
      this.setState('error', this.getStrings().waitAlert);
      this._button.focus();
      return;
    }

    if (this.getAutoMode() !== 'onsubmit') {
      event.preventDefault();
      event.stopPropagation();
      this.setState('error', this.getStrings().required);
      this._button.focus();
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const submitter = typeof event.submitter !== 'undefined' ? event.submitter : null;
    const verified = await this.startVerification();
    if (!verified || !this._form) {
      this._button.focus();
      return;
    }

    this._allowNextSubmit = true;
    try {
      if (typeof this._form.requestSubmit === 'function') {
        this._form.requestSubmit(submitter || undefined);
      } else if (submitter && typeof submitter.click === 'function') {
        submitter.click();
      } else {
        HTMLFormElement.prototype.submit.call(this._form);
      }
    } catch (error) {
      this._allowNextSubmit = false;
      throw error;
    }
  }

  async fetchChallenge() {
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

  async ensureChallenge() {
    if (this._challenge && !this.isChallengeExpired()) {
      return this._challenge;
    }

    this._challenge = await this.fetchChallenge();
    this._challengeIssuedAt = Date.now();
    return this._challenge;
  }

  async solveChallenge(challenge) {
    for (let number = 0; number <= challenge.maxnumber; number += 1) {
      if (await asfwSha256Hex(`${challenge.salt}${number}`) === challenge.challenge) {
        return String(number);
      }

      if (number > 0 && number % 250 === 0) {
        await asfwSleep(0);
      }
    }

    return null;
  }

  async startVerification() {
    if (this._verifyPromise) {
      return this._verifyPromise;
    }

    if (!window.crypto?.subtle) {
      this.setState('error', this.getStrings().error);
      return false;
    }

    this._verifyPromise = (async () => {
      try {
        this.setState('verifying');

        const challenge = await this.ensureChallenge();
        const number = await this.solveChallenge(challenge);
        if (number === null) {
          throw new Error('Challenge could not be solved.');
        }

        if (this.getDelayMs() > 0) {
          await asfwSleep(this.getDelayMs());
        }

        const minSubmitTimeMs = this.getMinSubmitTimeMs();
        if (minSubmitTimeMs > 0 && this._challengeIssuedAt > 0) {
          const remainingMs = minSubmitTimeMs - (Date.now() - this._challengeIssuedAt);
          if (remainingMs > 0) {
            await asfwSleep(remainingMs);
          }
        }

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
        console.error('ASFW widget verification failed.', error);
        this._valueInput.value = '';
        this._challenge = null;
        this.setState('error', this.getStrings().error);
        return false;
      } finally {
        this._verifyPromise = null;
      }
    })();

    return this._verifyPromise;
  }
}

if (!customElements.get('asfw-widget')) {
  customElements.define('asfw-widget', ASFWWidgetElement);
}
