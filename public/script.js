(() => {
  const runtime = typeof window.ASFW_RUNTIME === 'object' && window.ASFW_RUNTIME !== null
    ? window.ASFW_RUNTIME
    : {};

  const defaultFieldName = runtime.defaultFieldName || 'asfw';
  const submitDelayMessageTemplate = typeof runtime.submitDelayMessage === 'string' && runtime.submitDelayMessage.includes('%s')
    ? runtime.submitDelayMessage
    : 'Please wait %ss...';
  const submitButtonStates = new WeakMap();

  function getFieldName(el) {
    return el.getAttribute('name') || el.dataset.asfwField || defaultFieldName;
  }

  function getStartedFieldName(fieldName) {
    return `${fieldName}_started`;
  }

  function getHoneypotFieldName(fieldName) {
    return `${fieldName}_website`;
  }

  function ensureHiddenInput(form, name) {
    let input = form.querySelector(`input[name="${name}"]`);
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.appendChild(input);
    }

    return input;
  }

  function ensureHoneypotInput(form, fieldName) {
    if (!runtime.honeypotEnabled) {
      return;
    }

    const honeypotName = getHoneypotFieldName(fieldName);
    if (form.querySelector(`input[name="${honeypotName}"]`)) {
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'asfw-honeypot';
    wrap.setAttribute('aria-hidden', 'true');
    wrap.style.position = 'absolute';
    wrap.style.left = '-10000px';
    wrap.style.top = 'auto';
    wrap.style.width = '1px';
    wrap.style.height = '1px';
    wrap.style.overflow = 'hidden';

    const input = document.createElement('input');
    input.type = 'text';
    input.name = honeypotName;
    input.value = '';
    input.autocomplete = 'off';
    input.tabIndex = -1;
    wrap.appendChild(input);
    form.appendChild(wrap);
  }

  function initWidget(el) {
    if (el.dataset.asfwInitialized === '1') {
      return;
    }

    const form = el.closest('form');
    if (!form) {
      return;
    }

    const fieldName = getFieldName(el);
    const started = ensureHiddenInput(form, getStartedFieldName(fieldName));
    if (!started.value) {
      started.value = Date.now().toString();
    }

    ensureHoneypotInput(form, fieldName);
    el.dataset.asfwInitialized = '1';
  }

  function setSubmitButtonsDisabled(form, disabled) {
    const buttons = form.querySelectorAll('button:not([type]), button[type="submit"], button[type="image"], input[type="submit"], input[type="image"]');
    buttons.forEach((button) => {
      if (!submitButtonStates.has(button)) {
        submitButtonStates.set(button, button.disabled);
      }

      const wasDisabled = submitButtonStates.get(button);
      button.disabled = disabled ? true : wasDisabled;
      button.classList.toggle('asfw-submit-delay-active', disabled && !wasDisabled);
    });
  }

  function getSubmitDelayStatus(form) {
    return form.querySelector('.asfw-submit-delay-status[data-asfw-submit-delay-until]');
  }

  function getRemainingSubmitDelayMs(status) {
    const until = Number.parseInt(status?.getAttribute('data-asfw-submit-delay-until') || '0', 10);
    if (!Number.isFinite(until)) {
      return 0;
    }

    return until - Date.now();
  }

  function setSubmitDelayUntil(status, untilMs) {
    status.setAttribute('data-asfw-submit-delay-until', String(untilMs));
  }

  function getSubmitDelayMode(status) {
    return status.getAttribute('data-asfw-submit-delay-mode') === 'block' ? 'block' : 'log';
  }

  function getSubmitDelayRetryCount(form) {
    const count = Number.parseInt(form.dataset.asfwSubmitDelayRetryCount || '0', 10);
    return Number.isFinite(count) && count > 0 ? count : 0;
  }

  function setSubmitDelayRetryCount(form, count) {
    form.dataset.asfwSubmitDelayRetryCount = String(Math.max(0, count));
  }

  async function ensureFreshSubmitDelayToken(form, status) {
    if (status.dataset.asfwSubmitDelayTokenReady === '1') {
      return true;
    }
    if (status.dataset.asfwSubmitDelayTokenReady === 'pending') {
      return false;
    }

    const tokenUrl = status.getAttribute('data-asfw-submit-delay-token-url') || '';
    if (!tokenUrl) {
      status.dataset.asfwSubmitDelayTokenReady = '1';
      return true;
    }

    status.dataset.asfwSubmitDelayTokenReady = 'pending';
    try {
      const response = await fetch(tokenUrl, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
        cache: 'no-store',
      });
      if (!response.ok) {
        throw new Error(`Submit-delay token request failed: ${response.status}`);
      }

      const data = await response.json();
      const tokenId = typeof data?.token_id === 'string' ? data.token_id : '';
      const signature = typeof data?.signature === 'string' ? data.signature : '';
      const issuedAtMs = Number.parseInt(String(data?.issued_at_ms ?? '0'), 10);
      const delayMs = Number.parseInt(String(data?.delay_ms ?? status.getAttribute('data-asfw-submit-delay-ms') ?? '0'), 10);
      if (!tokenId || !signature || !Number.isFinite(issuedAtMs) || issuedAtMs <= 0 || !Number.isFinite(delayMs) || delayMs <= 0) {
        throw new Error('Submit-delay token response is invalid');
      }

      ensureHiddenInput(form, 'asfw_submit_delay_token').value = tokenId;
      ensureHiddenInput(form, 'asfw_submit_delay_signature').value = signature;
      status.setAttribute('data-asfw-submit-delay-ms', String(delayMs));
      setSubmitDelayUntil(status, issuedAtMs + delayMs);
      status.dataset.asfwSubmitDelayTokenReady = '1';
      return true;
    } catch (_error) {
      delete status.dataset.asfwSubmitDelayTokenReady;
      return false;
    }
  }

  function bindSubmitDelayGuard(form, status) {
    if (form.dataset.asfwSubmitDelayGuardBound === '1') {
      return;
    }

    form.addEventListener('submit', (event) => {
      if (getSubmitDelayMode(status) !== 'block') {
        return;
      }

      if (status.dataset.asfwSubmitDelayTokenReady === 'failed') {
        event.preventDefault();
        event.stopPropagation();
        status.textContent = 'Verification is preparing. Please try again.';
        status.classList.add('is-active');
        delete status.dataset.asfwSubmitDelayTokenReady;
        delete form.dataset.asfwSubmitDelayInitialized;
        setSubmitDelayRetryCount(form, 0);
        initSubmitDelay(form);
        return;
      }

      const remainingMs = getRemainingSubmitDelayMs(status);
      if (remainingMs <= 0) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      setSubmitButtonsDisabled(form, true);
      status.classList.add('is-active');
      const remainingSeconds = Math.max(1, Math.ceil(remainingMs / 1000));
      status.textContent = submitDelayMessageTemplate.replace('%s', String(remainingSeconds));
    }, true);

    form.dataset.asfwSubmitDelayGuardBound = '1';
  }

  function initSubmitDelay(form) {
    const status = getSubmitDelayStatus(form);
    if (!status) {
      return;
    }
    const strictBlockMode = getSubmitDelayMode(status) === 'block';
    bindSubmitDelayGuard(form, status);

    if (status.dataset.asfwSubmitDelayTokenReady === 'failed') {
      setSubmitDelayUntil(status, 0);
      if (strictBlockMode) {
        setSubmitButtonsDisabled(form, true);
        status.classList.add('is-active');
      } else {
        setSubmitButtonsDisabled(form, false);
        status.classList.remove('is-active');
      }
      status.textContent = strictBlockMode ? 'Verification is preparing. Please try again.' : '';
      form.dataset.asfwSubmitDelayInitialized = '1';
      return;
    }

    if (status.dataset.asfwSubmitDelayTokenReady !== '1') {
      if (strictBlockMode) {
        setSubmitButtonsDisabled(form, true);
        status.classList.add('is-active');
      } else {
        setSubmitButtonsDisabled(form, false);
        status.classList.remove('is-active');
      }
      if (!status.textContent) {
        status.textContent = 'Preparing submit...';
      }
      ensureFreshSubmitDelayToken(form, status).then((ready) => {
        const nextRetryCount = ready ? 0 : getSubmitDelayRetryCount(form) + 1;
        setSubmitDelayRetryCount(form, nextRetryCount);
        if (!ready && nextRetryCount >= 3) {
          setSubmitDelayUntil(status, 0);
          if (strictBlockMode) {
            setSubmitButtonsDisabled(form, true);
            status.classList.add('is-active');
          } else {
            setSubmitButtonsDisabled(form, false);
            status.classList.remove('is-active');
          }
          status.textContent = strictBlockMode ? 'Verification is preparing. Please try again.' : '';
          status.dataset.asfwSubmitDelayTokenReady = 'failed';
          delete form.dataset.asfwSubmitDelayInitialized;
          return;
        }

        const waitMs = ready ? 0 : Math.min(3000, nextRetryCount * 1000);
        window.setTimeout(() => {
          if (ready && !strictBlockMode) {
            setSubmitButtonsDisabled(form, false);
            status.textContent = '';
            status.classList.remove('is-active');
          }
          delete form.dataset.asfwSubmitDelayInitialized;
          initSubmitDelay(form);
        }, waitMs);
      });
      return;
    }

    const remainingAtInit = getRemainingSubmitDelayMs(status);
    if (remainingAtInit <= 0) {
      setSubmitDelayRetryCount(form, 0);
      setSubmitButtonsDisabled(form, false);
      status.textContent = '';
      status.classList.remove('is-active');
      form.dataset.asfwSubmitDelayInitialized = '1';
      return;
    }

    if (!strictBlockMode) {
      setSubmitButtonsDisabled(form, false);
      status.textContent = '';
      status.classList.remove('is-active');
      form.dataset.asfwSubmitDelayInitialized = '1';
      return;
    }

    if (form.dataset.asfwSubmitDelayInitialized === '1') {
      setSubmitButtonsDisabled(form, true);
      status.classList.add('is-active');
      const remainingSeconds = Math.max(1, Math.ceil(remainingAtInit / 1000));
      status.textContent = submitDelayMessageTemplate.replace('%s', String(remainingSeconds));
      return;
    }

    form.dataset.asfwSubmitDelayInitialized = '1';
    setSubmitButtonsDisabled(form, true);
    status.classList.add('is-active');
    let lastDisplayedSecond = null;

    const tick = () => {
      const remainingMs = getRemainingSubmitDelayMs(status);
      if (remainingMs <= 0) {
        setSubmitButtonsDisabled(form, false);
        status.textContent = '';
        status.classList.remove('is-active');
        return;
      }

      setSubmitButtonsDisabled(form, true);
      const remainingSeconds = Math.max(1, Math.ceil(remainingMs / 1000));
      if (remainingSeconds !== lastDisplayedSecond) {
        status.textContent = submitDelayMessageTemplate.replace('%s', String(remainingSeconds));
        lastDisplayedSecond = remainingSeconds;
      }
      window.setTimeout(tick, Math.min(remainingMs, 250));
    };

    tick();
  }

  function boot() {
    requestAnimationFrame(() => {
      [...document.querySelectorAll('asfw-widget')].forEach(initWidget);
      [...document.querySelectorAll('form')].forEach(initSubmitDelay);

      const observer = new MutationObserver(() => {
        [...document.querySelectorAll('asfw-widget')].forEach(initWidget);
        [...document.querySelectorAll('form')].forEach(initSubmitDelay);
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });

      window.addEventListener('pageshow', () => {
        [...document.querySelectorAll('form')].forEach((form) => {
          delete form.dataset.asfwSubmitDelayInitialized;
          delete form.dataset.asfwSubmitDelayRetryCount;
          const status = getSubmitDelayStatus(form);
          if (status) {
            delete status.dataset.asfwSubmitDelayTokenReady;
          }
          initSubmitDelay(form);
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
