(() => {
  const runtime = typeof window.ASFW_RUNTIME === 'object' && window.ASFW_RUNTIME !== null
    ? window.ASFW_RUNTIME
    : {};

  const defaultFieldName = runtime.defaultFieldName || 'asfw';

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

  document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
      [...document.querySelectorAll('asfw-widget')].forEach(initWidget);

      const observer = new MutationObserver(() => {
        [...document.querySelectorAll('asfw-widget')].forEach(initWidget);
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    });
  });
})();
