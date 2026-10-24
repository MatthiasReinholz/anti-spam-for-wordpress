(() => {
  'use strict';
  const config = window.ASFW_CHECKOUT;
  if (!config || !Array.isArray(config.fields)) return;
  // A page containing both checkout renderers may enqueue this shared adapter twice.
  const initialized = window.ASFW_CHECKOUT_ADAPTER || (window.ASFW_CHECKOUT_ADAPTER = {});
  if (!initialized.classic && typeof window.jQuery === 'function') {
    initialized.classic = true;
    window.jQuery(document.body).on('checkout_error.asfwCheckout', () => {
      const forms = new Set([...document.querySelectorAll('[data-asfw-checkout-classic]')].map(node => node.closest('form')).filter(Boolean));
      forms.forEach(form => form.dispatchEvent(new CustomEvent('asfw:submission-complete')));
    });
  }
  if (!config.blocks || initialized.blocks) return;
  const wp = window.wp;
  const wc = window.wc;
  const Slot = wc?.blocksCheckout?.ExperimentalOrderMeta;
  const store = wc?.wcBlocksData?.checkoutStore;
  const events = wc?.blocksCheckoutEvents?.checkoutEvents;
  if (!Slot || !store || !events?.onCheckoutValidation || !events?.onCheckoutFail ||
      !wp?.plugins?.registerPlugin || !wp?.element?.createElement || !wp?.element?.useEffect ||
      !wp?.element?.useRef || !wp?.data?.useSelect || !wp?.data?.dispatch) {
    const diagnostic = document.createElement('div');
    diagnostic.className = 'woocommerce-error';
    diagnostic.setAttribute('role', 'alert');
    diagnostic.textContent = config.compatibilityMessage;
    const checkout = document.querySelector('.wp-block-woocommerce-checkout');
    (checkout || document.body).prepend(diagnostic);
    return;
  }
  initialized.blocks = true;
  const { createElement, useEffect, useRef } = wp.element;

  function CheckoutProtection({ context }) {
    const ref = useRef(null);
    const customerId = wp.data.useSelect(select => select(store).getCustomerId(), []);
    useEffect(() => {
      if (context !== 'woocommerce/checkout' || customerId || !ref.current) return undefined;
      const form = ref.current;
      let disposed = false;
      const widget = () => form.querySelector('asfw-widget');
      const sync = () => {
        if (disposed) return;
        const values = {};
        const data = new FormData(form);
        config.fields.forEach(name => {
          const value = data.get(name);
          values[name] = typeof value === 'string' ? value : '';
        });
        // The public action accepts a whole namespace object. Keep other extensions intact.
        wp.data.dispatch(store).setExtensionData(config.namespace, values, true);
      };
      const prepare = async () => {
        await window.customElements.whenDefined('asfw-widget');
        if (disposed || !widget()) return;
        const element = widget();
        if (element.getAttribute('auto')) await element.startVerification();
        sync();
      };
      const renew = () => {
        if (disposed || !widget()) return;
        form.dispatchEvent(new CustomEvent('asfw:submission-complete'));
        // The shared reset adapter finishes renewing guards in its own microtask.
        window.setTimeout(() => { if (!disposed) void prepare(); }, 0);
      };
      form.addEventListener('asfw:state-change', sync);
      form.addEventListener('input', sync);
      // Automatic modes prepare before validation; observers must not mutate checkout state.
      void prepare();
      sync();
      const unsubscribeValidation = events.onCheckoutValidation(() => {
        const element = widget();
        const data = new FormData(form);
        if (element?.getState() === 'verified' && !element.isChallengeExpired() && data.get(config.field)) return true;
        window.setTimeout(() => {
          if (!disposed) {
            if (element?.isChallengeExpired()) renew();
            element?.focusVerificationControl();
          }
        }, 0);
        return { type: 'error', message: config.message };
      });
      const unsubscribeFailure = events.onCheckoutFail(() => {
        // A server/payment failure may have consumed the proof. Renew outside the emitter.
        window.setTimeout(renew, 0);
        return true;
      });
      return () => {
        disposed = true;
        unsubscribeValidation();
        unsubscribeFailure();
        form.removeEventListener('asfw:state-change', sync);
        form.removeEventListener('input', sync);
        wp.data.dispatch(store).setExtensionData(config.namespace, {}, true);
      };
    }, [context, customerId]);
    if (context !== 'woocommerce/checkout' || customerId) return null;
    // OrderMeta is outside the main Checkout form. This form owns widget fields only.
    return createElement('form', {
      ref, className: 'asfw-checkout-protection', 'aria-label': config.label,
      onSubmit: event => event.preventDefault(),
      dangerouslySetInnerHTML: { __html: config.markup },
    });
  }

  wp.plugins.registerPlugin('anti-spam-for-wordpress-checkout', {
    scope: 'woocommerce-checkout',
    render: () => createElement(Slot, null, createElement(CheckoutProtection)),
  });
})();
