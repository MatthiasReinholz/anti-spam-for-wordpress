(() => {
  function applyWidgetConfig(el, attrs) {
    if (typeof el.configure === 'function') {
      el.configure(attrs);
      return;
    }

    Object.entries(attrs).forEach(([key, value]) => {
      if (value === null || value === undefined || value === false || value === '') {
        return;
      }

      if (key.startsWith('data-')) {
        el.setAttribute(key, String(value));
        return;
      }

      el.setAttribute(key, String(value));
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
      if (!('ASFW_WIDGET_ATTRS' in window) || typeof window.ASFW_WIDGET_ATTRS !== 'object') {
        return;
      }

      [...document.querySelectorAll('asfw-widget')].forEach((el) => {
        applyWidgetConfig(el, { ...window.ASFW_WIDGET_ATTRS });
      });
    });
  });
})();
