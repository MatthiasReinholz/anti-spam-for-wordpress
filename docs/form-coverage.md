# Form coverage implementation and rollout

Version 0.11.0 introduced the integrations below; 0.11.1 hardens checkout route
matching and Contact Form 7 validation. Installing a release and enabling
site policies are separate steps: activation alone does not enable every
placement. Verify each configured public form after deployment.

## Integration design

1. Share one verifier between posted forms and explicit unslashed fields from
   JSON/custom transports. Preserve single-use state, quotas, visitor binding,
   optional supported guards and private event analysis without mutating globals.
2. Add native classic and Blocks/Store API guest checkout dispatch integration,
   distinct from account registration. Validate before account/order processing,
   after WordPress route permissions, without consuming a proof during gateway preflight checks.
3. Guard native multisite signup stages and actual core REST comment insertion.
   Derive manual CF7 policy from the saved current form template. Preserve rendering
   defaults and documented context filters, direct programmatic creation and
   authenticated moderation capabilities.
4. Applications configure their placements explicitly and use the success-only
   verification signal for dependent actions. Site owners control unused
   intake and discussion/pingback policy.
5. Complete offline and real integration regression checks for each supported
   provider. Deploy the compatible release to all serving processes before
   applying new placement settings. Verify effective settings and fresh public
   markup separately from code availability.

## Policy and transport matrix

| Surface | Policy and boundary |
| --- | --- |
| Native WordPress register | Existing registration placement and `register_post` |
| Native multisite user/site signup | Same placement; `wordpress:multisite-register` at native signup dispatcher stages; fresh proof per form |
| WooCommerce account register | Existing registration placement and `woocommerce:register`; no URL-only gate |
| Classic guest checkout | Separate checkout placement; `woocommerce:checkout` / `asfw_checkout` at the actual authorized checkout dispatch |
| Checkout Blocks / Store API | Same guest policy; parsed extension fields checked before POST checkout callback, including batch requests and case-insensitive route aliases |
| Account login/reset | Separate existing placements; verify every rendered account/modal/checkout login surface |
| CF7 automatic | Fixed server-owned `contact-form-7` context; provider validation also covers subscriber-only and skipped spam/mail paths |
| CF7 shortcode | Same validation boundary; exactly one saved effective widget; derive context/name with the same defaults and filters as rendering |
| Native comments | Existing comment placement and actual browser submission markers |
| Core REST comments | Same comment policy; ordinary authenticated customers require JSON/body proof; moderators exempt |
| Direct programmatic creation | No blanket browser challenge on user/comment insertion APIs |
| Custom newsletter/restock | Application renders a fixed context and verifies fields before side effects; own consent, recipient quotas and idempotency |
| Pingback/trackback | Not a browser proof integration; site owners should disable unused methods and pings explicitly |

The native emergency kill switch bypasses native placement enforcement consistently.
The explicit-fields helper fails closed, so custom intake adapters can distinguish
available protection from an emergency bypass. A bypass never emits the verified
submission signal.

Proof of work raises automation cost. It does not establish email ownership,
replace authorization/CSRF checks, prevent distributed low-rate abuse, or prove a
subscriber's consent. Optional disposable-email checks need maintained data and
reviewed exception policy. Heuristics remain observational where documented.

## Tested commerce capability boundary

The real integration fixture uses WordPress 7.1.3 / PHP 8.4 and WooCommerce 11.2.1,
with the consuming application's Revolut adapter when supplied. Blocks requires
WooCommerce's checkout-events export, checkout data store extension setter,
OrderMeta SlotFill and Store API checkout extension schema. This is a capability
contract, not a claim that every historical Woo release is supported. Unsupported
configured Blocks runtimes must surface an actionable error rather than bypass
server verification. Validate each additional express/headless client before use.

Classic checkout, Checkout Blocks and the native account signup form support
visible manual, visible automatic and invisible presentation. Both placements can
be enabled together: signup uses `woocommerce:register` / `asfw_register`, while
account creation during checkout uses `woocommerce:checkout` / `asfw_checkout`.
The shared renderer accepts invisible presentation through the context-aware
`asfw_widget_attrs` filter; see the [configuration example](../README.md#visible-and-invisible-presentation).
Invisible verification prepares automatically, preserves the same server proof
checks and exposes an accessible error/retry control on failure.

## Acceptance and rollback

The real CPT/HPOS fixture is `scripts/test-form-coverage.sh`; supply reviewed Woo and
gateway plugin directories as documented in [development](development.md#real-commerce-regression).
It complements the fast PHPUnit/browser tests and is part of checkout release review.
The CF7 counterpart is `scripts/test-contact-form-coverage.sh`, with reviewed provider
source supplied through `ASFW_CF7_SOURCE`; see the
[provider lifecycle test](development.md#real-contact-form-7-regression).

Exercise missing, malformed, wrong-context, expired, replayed and valid proofs;
normal and JSON transports; ordinary authenticated and moderation clients; both
multisite signup stages; existing-user site signup; CF7 subscriber-only and skipped
spam/mail paths; mixed-case checkout routes; classic/Blocks and enabled
payment/express flows; validation/preflight/payment failure and retry; and cached
or already-open forms. Verify rejection precedes account creation, email or final
order submission. Run mutation tests only in disposable databases with mail and
external requests intercepted.

Event logging is separate from protection. Enable it deliberately, choose bounded
retention and inspect per-context decisions before tuning quotas. Watch successful
registrations and dependent email enqueueing as application outcomes as well.

Keep site settings auditable and repeatable. If a deployment needs rollback, use
the verified previous package and policy, preserve plugin data and refresh form
assets. Do not uninstall as a rollback procedure. New checkout settings must not
be assumed enforced after rolling back to a package without that integration.
