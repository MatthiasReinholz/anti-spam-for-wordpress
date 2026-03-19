(() => {
  document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
      if ('ASFW_WIDGET_ATTRS' in window && typeof window.ASFW_WIDGET_ATTRS === 'object') {
        [...document.querySelectorAll('altcha-widget')].forEach((el) => {
          if (typeof el.configure === 'function' && !el.getAttribute('challengeurl')) {
            el.configure(window.ASFW_WIDGET_ATTRS);
          }
        });
      }
    });
  });
})();
