(() => {
  const runtime = typeof window.ASFW_RUNTIME === 'object' && window.ASFW_RUNTIME !== null ? window.ASFW_RUNTIME : {};
  const forms = new WeakMap();
  const widgetForms = new WeakMap();
  const requestTimeoutMs = 10000;
  const widgetSelector = 'asfw-widget';
  const guardSelector = '.asfw-submit-delay-status, .asfw-math-challenge';
  const buttonSelector = 'button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]';
  const strings = {
    preparing: runtime.guardPreparing || 'Preparing verification...',
    error: runtime.guardError || 'Verification could not be prepared. Try again.',
    retry: runtime.guardRetry || 'Try again',
    delay: runtime.submitDelayMessage || 'Please wait %s s...',
    math: runtime.mathQuestion || '%1$s + %2$s = ?',
  };

  function setText(element, text) {
    if (element.textContent !== text) element.textContent = text;
  }

  function inputFor(form, name) {
    let input = [...form.querySelectorAll('input')].find(candidate => candidate.name === name);
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.appendChild(input);
    }
    return input;
  }

  function initializeWidget(widget, form, renew = false) {
    const name = widget.getAttribute('name') || widget.dataset.asfwField || runtime.defaultFieldName || 'asfw';
    const initialized = widgetForms.get(widget);
    if (!renew && initialized?.form === form && initialized.name === name) return;
    inputFor(form, `${name}_started`).value = String(Date.now());
    if (runtime.honeypotEnabled && ![...form.querySelectorAll('input')].some(input => input.name === `${name}_website`)) {
      const wrap = document.createElement('div');
      wrap.className = 'asfw-honeypot';
      wrap.setAttribute('aria-hidden', 'true');
      const input = document.createElement('input');
      input.type = 'text';
      input.name = `${name}_website`;
      input.autocomplete = 'off';
      input.tabIndex = -1;
      wrap.appendChild(input);
      form.appendChild(wrap);
    }
    widgetForms.set(widget, { form, name });
  }

  // Only restore a button while we still own its disabled state. Provider writes
  // during the wait revoke that ownership, including assigning disabled=true again.
  function updateButtons(state) {
    const waiting = [...state.guards.values()].some(guard => guard.mode === 'block' && ['pending', 'waiting'].includes(guard.phase));
    if (waiting) {
      state.form.querySelectorAll(buttonSelector).forEach(button => {
        if (button.disabled || state.buttons.has(button)) return;
        button.disabled = true;
        button.classList.add('asfw-submit-delay-active');
        const lease = { changed: false, observer: null };
        lease.observer = new MutationObserver(() => { lease.changed = true; });
        lease.observer.observe(button, { attributes: true, attributeFilter: ['disabled'] });
        state.buttons.set(button, lease);
      });
    } else {
      for (const [button, lease] of state.buttons) {
        const changed = lease.changed || lease.observer.takeRecords().length > 0;
        lease.observer.disconnect();
        if (!changed) button.disabled = false;
        button.classList.remove('asfw-submit-delay-active');
      }
      state.buttons.clear();
    }
  }

  function clearGuardFields(form, kind) {
    const names = kind === 'math'
      ? ['asfw_math_challenge', 'asfw_math_signature', 'asfw_math_answer']
      : ['asfw_submit_delay_token', 'asfw_submit_delay_signature'];
    names.forEach(name => { inputFor(form, name).value = ''; });
  }

  function stopGuard(guard) {
    guard.generation += 1;
    guard.controller?.abort();
    guard.controller = null;
    guard.promise = null;
    window.clearTimeout(guard.timer);
    guard.timer = null;
  }

  function renderGuard(state, guard) {
    const waiting = guard.phase === 'waiting';
    const failed = guard.phase === 'failed';
    const message = failed ? strings.error : guard.phase === 'pending' ? strings.preparing : waiting
      ? strings.delay.replace('%s', String(Math.max(1, Math.ceil((guard.until - Date.now()) / 1000)))) : '';
    setText(guard.status, message);
    guard.status.classList.toggle('is-active', message !== '');
    guard.status.classList.toggle('is-pending', guard.phase === 'pending' || waiting);
    guard.retry.hidden = !failed;
    updateButtons(state);
  }

  function tickDelay(state, guard) {
    if (!state.form.isConnected || !state.form.contains(guard.element)) return;
    if (Date.now() >= guard.until) guard.phase = 'ready';
    renderGuard(state, guard);
    if (guard.phase === 'waiting') guard.timer = window.setTimeout(() => tickDelay(state, guard), Math.min(250, guard.until - Date.now()));
  }

  function currentGuard(state, guard, generation) {
    return state.form.isConnected && state.form.contains(guard.element) &&
      state.guards.get(guard.kind) === guard && guard.generation === generation;
  }

  function prepareGuard(state, guard) {
    if (guard.promise) return guard.promise;
    stopGuard(guard);
    clearGuardFields(state.form, guard.kind);
    const generation = guard.generation;
    const controller = new AbortController();
    guard.controller = controller;
    guard.phase = 'pending';
    guard.expiresAt = 0;
    renderGuard(state, guard);
    guard.promise = (async () => {
      const timeout = window.setTimeout(() => controller.abort(), requestTimeoutMs);
      try {
        if (!guard.url) throw new Error('Missing guard URL.');
        const response = await fetch(guard.url, {
          credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal,
        });
        if (!response.ok) throw new Error('Guard request failed.');
        const data = await response.json();
        if (!currentGuard(state, guard, generation)) return;
        const signature = typeof data?.signature === 'string' ? data.signature : '';
        const expiresAt = Number(data?.expires_at) * 1000;
        if (!signature || !Number.isFinite(expiresAt) || expiresAt <= Date.now()) throw new Error('Invalid guard expiry.');
        if (guard.kind === 'delay') {
          const delayMs = Number(data?.delay_ms);
          if (typeof data?.token_id !== 'string' || !data.token_id || !Number.isFinite(delayMs) || delayMs <= 0) throw new Error('Invalid delay token.');
          inputFor(state.form, 'asfw_submit_delay_token').value = data.token_id;
          inputFor(state.form, 'asfw_submit_delay_signature').value = signature;
          // Waiting from receipt is conservative when the browser and server clocks differ.
          guard.until = Date.now() + delayMs;
          guard.element.setAttribute('data-asfw-submit-delay-until', String(guard.until));
          guard.phase = guard.mode === 'block' ? 'waiting' : 'ready';
        } else {
          if (typeof data?.challenge_id !== 'string' || !data.challenge_id || !Number.isSafeInteger(data.left) || !Number.isSafeInteger(data.right)) throw new Error('Invalid math challenge.');
          inputFor(state.form, 'asfw_math_challenge').value = data.challenge_id;
          inputFor(state.form, 'asfw_math_signature').value = signature;
          const label = guard.element.querySelector('.asfw-math-question');
          if (!label) throw new Error('Missing math question label.');
          setText(label, strings.math.replace('%1$s', String(data.left)).replace('%2$s', String(data.right)));
          guard.phase = 'ready';
        }
        guard.expiresAt = expiresAt;
        if (guard.phase === 'waiting') tickDelay(state, guard);
        else renderGuard(state, guard);
      } catch (_error) {
        if (!currentGuard(state, guard, generation)) return;
        clearGuardFields(state.form, guard.kind);
        guard.phase = 'failed';
        renderGuard(state, guard);
      } finally {
        window.clearTimeout(timeout);
        if (guard.generation === generation) {
          guard.promise = null;
          guard.controller = null;
        }
      }
    })();
    return guard.promise;
  }

  function reconcileGuards(state) {
    for (const kind of ['delay', 'math']) {
      const element = state.form.querySelector(kind === 'delay' ? '.asfw-submit-delay-status' : '.asfw-math-challenge');
      const url = element?.getAttribute(kind === 'delay' ? 'data-asfw-submit-delay-token-url' : 'data-asfw-math-challenge-url') || '';
      const mode = element?.getAttribute(kind === 'delay' ? 'data-asfw-submit-delay-mode' : 'data-asfw-math-mode') === 'block' ? 'block' : 'log';
      const old = state.guards.get(kind);
      if (old && old.element === element && old.url === url && old.mode === mode) continue;
      if (old) {
        stopGuard(old);
        old.retry.remove();
        if (kind === 'math') old.status.remove();
        state.guards.delete(kind);
      }
      if (!element) continue;
      const status = kind === 'delay' ? element : document.createElement('span');
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      if (kind === 'math') {
        status.className = 'asfw-guard-status';
        element.appendChild(status);
      }
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'asfw-guard-retry';
      retry.hidden = true;
      setText(retry, strings.retry);
      status.after(retry);
      const guard = { kind, element, status, retry, url, mode, generation: 0, phase: 'idle', until: 0, expiresAt: 0, controller: null, promise: null, timer: null };
      retry.addEventListener('click', () => { void prepareGuard(state, guard); });
      state.guards.set(kind, guard);
      void prepareGuard(state, guard);
    }
    updateButtons(state);
  }

  function renew(state) {
    if (!state.form.isConnected) return;
    state.submitted = false;
    state.generation += 1;
    state.providerAttempt = null;
    state.form.querySelectorAll(widgetSelector).forEach(widget => initializeWidget(widget, state.form, true));
    state.form.dispatchEvent(new CustomEvent('asfw:renew'));
    for (const guard of state.guards.values()) {
      stopGuard(guard);
      void prepareGuard(state, guard);
    }
  }

  function queueRenew(form, resetEvent = null) {
    const state = forms.get(form);
    if (!state) return;
    state.renewRequests.push(resetEvent);
    if (state.renewTimer !== null) return;
    // The native reset default action and provider handlers must finish first.
    state.renewTimer = window.setTimeout(() => {
      state.renewTimer = null;
      const requested = state.renewRequests.some(event => event === null || !event.defaultPrevented);
      state.renewRequests = [];
      if (requested) renew(state);
    }, 0);
  }

  function initializeForm(form) {
    if (!(form instanceof HTMLFormElement) || !form.isConnected) return;
    let state = forms.get(form);
    if (!state) {
      if (!form.querySelector(`${widgetSelector}, ${guardSelector}`)) return;
      state = { form, guards: new Map(), buttons: new Map(), submitted: false, generation: 0, providerAttempt: null, renewTimer: null, renewRequests: [] };
      forms.set(form, state);
      form.addEventListener('reset', event => queueRenew(form, event));
      form.addEventListener('asfw:submission-complete', event => {
        if (event.target === form) queueRenew(form);
      });
      form.addEventListener('submit', event => {
        const blocked = [...state.guards.values()].find(guard => {
          if (guard.phase === 'ready' && guard.expiresAt <= Date.now()) void prepareGuard(state, guard);
          return guard.mode === 'block' && guard.phase !== 'ready';
        });
        if (blocked) {
          event.preventDefault();
          event.stopImmediatePropagation();
          if (blocked.phase === 'failed') blocked.retry.focus();
          return;
        }
        const widgets = [...form.querySelectorAll(widgetSelector)];
        if (!event.defaultPrevented && widgets.every(widget => widget.getState?.() === 'verified')) state.submitted = true;
      }, true);
    }
    form.querySelectorAll(widgetSelector).forEach(widget => initializeWidget(widget, form));
    reconcileGuards(state);
    bindWpDiscuzForm(state);
  }

  function scan(root) {
    if (!(root instanceof Element) && root !== document) return;
    if (root instanceof Element) initializeForm(root.closest('form'));
    root.querySelectorAll('form').forEach(initializeForm);
  }

  function cleanup(root) {
    if (!(root instanceof Element)) return;
    const removedForms = root.matches('form') ? [root, ...root.querySelectorAll('form')] : [...root.querySelectorAll('form')];
    removedForms.forEach(form => {
      const state = forms.get(form);
      if (!state || form.isConnected) return;
      window.clearTimeout(state.renewTimer);
      state.renewTimer = null;
      state.renewRequests = [];
      state.generation += 1;
      state.providerAttempt = null;
      for (const guard of state.guards.values()) {
        stopGuard(guard);
        guard.phase = 'idle';
        guard.retry.remove();
        if (guard.kind === 'math') guard.status.remove();
      }
      state.guards.clear();
      updateButtons(state);
    });
  }

  function completeWithin(target) {
    const element = target?.jquery ? target[0] : target;
    if (!(element instanceof Element)) return;
    const form = element.matches('form') ? element : element.closest('form');
    if (form) queueRenew(form);
    else element.querySelectorAll('form').forEach(candidate => queueRenew(candidate));
  }

  function gravityRendered(formId) {
    const form = document.getElementById(`gform_${formId}`);
    if (!form) return;
    const submitted = forms.get(form)?.submitted;
    initializeForm(form);
    if (submitted) queueRenew(form);
  }

  function bindWpDiscuzForm(state) {
    const jquery = window.jQuery;
    if (state.wpdiscuzBound || typeof jquery !== 'function') return;
    state.wpdiscuzBound = true;
    // wpDiscuz delegates these clicks to body and may trigger them through
    // jQuery for Ctrl+Enter. A form-level jQuery handler catches both paths
    // before the provider serializes the form or marks its button in flight.
    jquery(state.form).on('click.asfw', '.wc_comm_submit.wpd_not_clicked, .wpd-inline-submit.wpd_not_clicked', event => {
      const button = event.currentTarget;
      if (button.closest('form') !== state.form) return;
      const blocked = [...state.guards.values()].find(guard => {
        if (guard.phase === 'ready' && guard.expiresAt <= Date.now()) void prepareGuard(state, guard);
        return guard.mode === 'block' && guard.phase !== 'ready';
      });
      const widgets = [...state.form.querySelectorAll(widgetSelector)];
      widgets.forEach(widget => { if (widget.isChallengeExpired?.()) widget.reset(); });
      const pending = widgets.filter(widget => widget.getState?.() !== 'verified' || !widget.querySelector('.asfw-hidden-value')?.value);
      if (!blocked && !pending.length) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      if (blocked) {
        if (blocked.phase === 'failed') blocked.retry.focus();
        return;
      }
      if (state.providerAttempt) return;
      const manual = pending.find(widget => widget.getAutoMode?.() !== 'onsubmit');
      if (manual) {
        if (manual.getState?.() !== 'verifying') manual.setState?.('error', manual.getStrings().required);
        manual.shadowRoot?.querySelector('.asfw-control')?.focus();
        return;
      }
      const attempt = { generation: state.generation };
      state.providerAttempt = attempt;
      void Promise.all(pending.map(widget => widget.startVerification())).then(results => {
        if (results.some(result => !result) || state.providerAttempt !== attempt || state.generation !== attempt.generation ||
            !state.form.isConnected || button.closest('form') !== state.form ||
            widgets.some(widget => widget.closest('form') !== state.form || widget.getState() !== 'verified' || widget.isChallengeExpired())) return;
        // The re-entered click rechecks current guard state, then reaches the
        // original vendor handler with complete, unconsumed credentials.
        jquery(button).trigger('click');
      }).finally(() => {
        if (state.providerAttempt === attempt) state.providerAttempt = null;
      });
    });
  }

  function completeWpDiscuzInline(settings) {
    const data = settings?.data;
    if (!(data instanceof FormData) || data.get('action') !== 'wpdAddInlineComment') return;
    const inlineId = String(data.get('inline_form_id') || '');
    if (!inlineId) return;
    document.querySelectorAll('form.wpd_inline_comm_form').forEach(form => {
      const wrapperId = form.closest('.wpd-inline-shortcode')?.id || '';
      if (!wrapperId || wrapperId.slice(wrapperId.lastIndexOf('-') + 1) !== inlineId || !forms.has(form)) return;
      // The vendor only restores this class on a parsed response. A completed
      // transport error must also permit an explicit retry of the inline form.
      form.querySelector('.wpd-inline-submit')?.classList.add('wpd_not_clicked');
      queueRenew(form);
    });
  }

  function bindProviders() {
    document.addEventListener('wpcf7submit', event => completeWithin(event.target));
    // HTML Forms dispatches a non-bubbling event on the submitted form.
    document.addEventListener('hf-submitted', event => completeWithin(event.target), true);
    document.addEventListener('gform/post_render', event => gravityRendered(event.detail?.formId));
    const jquery = window.jQuery;
    if (typeof jquery !== 'function') return;
    jquery(document).on('gform_post_render.asfw', (_event, formId) => gravityRendered(formId));
    jquery(document).on('forminator:form:submit:complete.asfw', event => completeWithin(event.target));
    jquery(document).on('wpformsAjaxSubmitCompleted.asfw', event => completeWithin(event.target));
    jquery(document).on('frmFormErrors.asfw', (_event, form) => completeWithin(form));
    jquery(document).on('ajaxComplete.asfw', (_event, _request, settings) => completeWpDiscuzInline(settings));
    jquery(document.body).on('wpdiscuz_comment_post_complete.asfw wpdiscuz_comment_post_failed.asfw', (_event, form) => completeWithin(form));
  }

  function boot() {
    scan(document);
    bindProviders();
    const observer = new MutationObserver(records => {
      const changedForms = new Set();
      for (const record of records) {
        if (record.type === 'attributes') {
          const form = record.target.closest('form');
          if (form) changedForms.add(form);
          continue;
        }
        record.removedNodes.forEach(cleanup);
        record.addedNodes.forEach(node => { if (node instanceof Element) scan(node); });
        if ([...record.removedNodes].some(node => node instanceof Element)) {
          const form = record.target.closest?.('form');
          if (form) changedForms.add(form);
        }
      }
      changedForms.forEach(initializeForm);
    });
    observer.observe(document.body, {
      childList: true, subtree: true, attributes: true,
      attributeFilter: ['name', 'data-asfw-field', 'data-asfw-submit-delay-token-url', 'data-asfw-submit-delay-mode', 'data-asfw-math-challenge-url', 'data-asfw-math-mode'],
    });
    window.addEventListener('pageshow', event => {
      if (event.persisted) document.querySelectorAll('form').forEach(form => queueRenew(form));
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
