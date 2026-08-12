# Paid Ticketing — Direct Processor Integration (Phase 1 Spec)

> Status: **Implemented — Phase 1 shipped on `v2-pmts`.** Direction: **Option C — direct processor integration, no WooCommerce dependency for paid tickets.** The WooCommerce checkout bridge and product mapping were fully removed; paid tickets route directly to Stripe / ePay.bg.

## 1. Goals & non-goals (Phase 1)

**Goals**
- Sell paid tickets with a branded checkout (hosted payment pages, PCI SAQ-A — plugin never touches card data).
- Zero hard dependency on WooCommerce for paid events.
- Global + Bulgaria coverage in EUR: **Stripe Checkout** (global cards, Apple/Google Pay) + **ePay.bg** (BG bank cards, BORICA-backed, lowest local interchange). myPOS is Phase 2.
- Fix the real gaps the WC path has: **capacity holds during payment**, **refund wiring that actually moves money**, **return-page pending state**.

**Non-goals (Phase 1)**
- No myPOS processor (Phase 2 — RSA verify, hosted Checkout).
- No per-ticket partial refunds (Phase 1 = full-order refunds only, ledger reserved for later).
- No multi-currency (EUR only; currency stored per order so it can be extended).
- No per-merchant onboarding / platform payouts (Phase 3).
- No Composer SDKs. Raw `wp_remote_post` to Stripe API; manual signature verification (matches plugin's vendoring style, zero runtime deps).

## 2. Architecture overview

```
Ticket Box AJAX (paid event)
   │  validate (capacity+exclusivity) → create order (pending) → reserve seats → create hosted session
   ▼
Payment_Processor (interface)
   ├── Stripe_Processor   (Checkout Session, HMAC-SHA256 webhook, global)
   └── Epay_Processor     (hosted page, signed IPN, plaintext reply, BG)
        │  webhook/IPN → verify signature → verify amount+currency → idempotency check
        ▼
Payment_Order_Service   (evt_order CPT)
   │  pending → paid → issue → refunded / expired / failed
   ▼
Ticket_Issuance_Service  ← extracted single source of truth
   │  capacity (tickets+holds), exclusivity, per-email limit, timeslot validity, lock, rollback, email
   ▼
existing Ticket_Service / Email_Service / Event_Capacity / Event_Timeslot_Capacity
```

### 2.1 Seam (final state)

- `Plugin::payment_service()` returns the `Payment_Service` facade (direct processors only — no WooCommerce). The old `Plugin::payments()` WooCommerce seam was removed along with `WooCommerce_Checkout_Service` / `WooCommerce_Order_Ticketing`.
- `Ticket_Issuance_Service` is the single source of truth for issuance (timeslot validity, exclusivity, per-email limit, capacity, rollback on failure, email send). The webhook handler and the free-path inline issuance in `Ticket_Box_Ajax` both route through it.
- The Payments settings tab exposes the processor schema under `evt_tickets_payments_processors_section`; the processor status block lives in `includes/admin/class-payment-processor-settings.php`.

## 3. Data model — `evt_order` CPT

Registered in a new `class-cpt-orders.php` (mirrors `CPT_Tickets` patterns).

- **Registration**: `public => false`, `show_ui => true` (admin list for organizers), `exclude_from_search => true`, **no REST** (`show_in_rest => false`), custom capabilities mapped to the plugin's existing `evt_manage_plugin` / event caps, `supports => ['' ]`, `rewrite => false`.
- **Post statuses** (registered custom statuses, `public => false`, `exclude_from_search => true`):
  - `evt_pending` — payment session created, seats held.
  - `evt_paid` — webhook verified, tickets issued.
  - `evt_failed` — processor decline / user abandoned (seats released).
  - `evt_expired` — hold TTL elapsed (seats released).
  - `evt_refunded` — full refund processed (tickets cancelled).
- **Order meta** (all stored on the order post):
  - `_evt_order_event_id`, `_evt_order_timeslot_id`, `_evt_order_quantity`
  - `_evt_order_attendee_name/_email/_phone`
  - `_evt_order_amount` (minor units, int), `_evt_order_currency` (EUR default, stored per order)
  - `_evt_order_processor` (`stripe` | `epay`)
  - `_evt_order_transaction_id` (processor id), `_evt_order_payment_ref`
  - `_evt_order_idempotency_key` (unique — processor event/transaction id)
  - `_evt_order_public_key` (random unguessable key for the return-page status endpoint)
  - `_evt_order_hold_expires_at` (unix ts)
  - `_evt_order_refunded_amount` (minor units, int ledger — starts 0, allows partials later)
  - `_evt_order_ticket_ids` (json array of issued ticket ids)
  - `_evt_order_issued_at`, `_evt_order_refunded_at`
- **Uninstall decision**: `uninstall.php` currently deletes options but intentionally leaves event/ticket *posts*. `evt_order` holds transactional/financial data (amounts, processor txn ids) — recommend **deleting all `evt_order` posts + their meta on uninstall** (unlike events/tickets), because payment records should not linger without the plugin to interpret them. Add `evt_order` capabilities to the role-caps cleanup list.

## 4. Capacity reservation model (core decision — resolved)

**Model: hold seats at checkout-begin, TTL, sweep, auto-refund on late payment.**

- **New `Reservation_Service`** guards a hold counter per event and per slot (not derived from order posts on every read — counters avoid N+1 meta queries and keep the existing 60s transient pattern).
  - `reserve(event_id, timeslot_id, qty)`: acquire `Event_Lock` (option/DB-backed, cross-request — already exists), re-check remaining = `tickets + active holds`, increment counters. Fail `409` on shortfall.
  - `release(event_id, timeslot_id, qty)`: decrement counters (used on `failed`/`expired`/user-cancel before payment).
  - `confirm(event_id, timeslot_id, qty)`: decrement once on webhook-paid (hold → ticket).
  - `expire_stale()`: `wp_cron` (interval 5 min) finds `evt_pending` orders with `hold_expires_at < now` → release + mark `evt_expired`.
- **Front-end `remaining`** (`Event_Timeslot_Capacity::remaining()` + event-level) subtracts active holds, so the Ticket Box never offers seats that are only held.
- **Late-payment edge**: webhook arrives for an order already `evt_expired` → do **not** issue; trigger `processor->refund()` (money moved, no seat) + email the attendee "order expired, refund initiated". This is the honest behavior and the whole point of the model.
- **Config**: hold TTL setting, default 30 min.
- **Event_Lock reuse**: wrap `reserve`/`release`/`confirm` and the webhook's issue step in `Event_Lock` to keep counter updates race-free (single-writer per event).

## 5. `Payment_Processor` interface

```php
interface Payment_Processor {
    // Create hosted payment session for an order. Returns redirect_url + session ref.
    public function create_payment(Order_Record $order): array|WP_Error;
    // Validate a webhook/IPN request. Returns normalized Payment_Event (id, status,
    // amount_minor, currency, transaction_id, raw) or WP_Error on invalid signature.
    public function verify_webhook(WP_REST_Request $request): Payment_Event|WP_Error;
    // Phase 1: full-order refund only. Amount in minor units (== order total).
    public function refund(string $transaction_id, int $amount_minor, string $currency): Result|WP_Error;
    // Server-side check on return page (Stripe: retrieve Session). ePay/myPOS: optional.
    public function verify_return(string $session_ref): ?Payment_Event;
}
```

- **Stripe_Processor**: Checkout Session via raw `wp_remote_post` to `https://api.stripe.com/v1/checkout/sessions`. Webhook = `stripe-webhooks` signature (HMAC-SHA256, verify with timestamp tolerance ±5 min, use `hash_equals`). `verify_return()` retrieves the Session server-side to shorten the pending window.
- **Epay_Processor**: hosted payment page (ePay.bg + BORICA schemes). IPN = signed POST; reply **plaintext** `INVOICE=<invoice>:STATUS=OK` (or `STATUS=ERR`). No HMAC — ePay's own signature scheme (secret + invoice fields).
- **MyPos_Processor** (Phase 2): hosted myPOS Checkout; IPN verification with **RSA public key**, not HMAC — explicitly scheduled, not assumed.

## 6. Webhook endpoints & idempotency

- **REST routes** (`register_rest_route`, `namespace: evt/v1`), **not admin-ajax**:
  - `POST evt/v1/payments/<processor>/webhook` — public (no nonce; signature is the auth). Route registration must be early (before `rest_api_init` usage, hook into `rest_api_init` in `Plugin::init()`).
  - `GET evt/v1/orders/<public_key>` — public status lookup for return-page polling (returns status + issued ticket count; no PII beyond what the holder already knows).
- **Cache/WAF**: document `wp-config.php` `REST_API_IP`/`REST` exclusions for common cache plugins + host WAF (Cloudflare rule to allow `POST` to `/wp-json/evt/v1/payments/*`). Include a "test webhook" button in settings that calls `expire_stale()` + shows last webhook log entry.
- **Idempotency**: key = processor event/transaction id stored in `_evt_order_idempotency_key` (unique meta, index). Duplicate event → return `200` no-op. Never the `'widget_' . time()` pattern. Order status transitions are guarded so `paid` is only entered once.
- **Security on webhook**: verify signature **and** `amount_minor` + `currency` match the order **before** marking paid.

## 7. Return-page pending state

- Flow: user returns from hosted page → Ticket Box success view → JS polls `GET evt/v1/orders/<public_key>` (interval ~3s, timeout ~60s).
- States rendered:
  - `evt_paid` → "Tickets are on their way" + link to ticket email.
  - `evt_pending` → "Seat held — payment received, tickets will arrive shortly" (polling continues).
  - `evt_expired`/`evt_failed` → clear failure + (if money moved) "refund initiated".
- Stripe: on return, `verify_return()` retrieves the Session — if already `paid`, mark issued immediately (kills most of the lag). ePay IPN can lag longer; polling covers it.

## 8. Secrets & settings

- **New `secret` field type** in `class-settings-store.php`:
  - `sanitize_field()`: `case 'secret'` → `sanitize_text_field` (raw value never echoed in markup).
  - Renderer: password-style input showing only last 4 chars + "show/reveal" toggle + "saved" indicator; value sent to server only when changed (empty submission keeps existing).
  - **Never** expose secrets via any REST settings endpoint or admin-ajax response.
- **`wp-config.php` override**: constants take precedence over stored options, e.g. `EVT_STRIPE_SECRET_KEY`, `EVT_EPAY_SECRET`, `EVT_EPAY_MERCHANT_ID`. Documented in the settings help text.
- **Payments tab additions**: active processor picker (`stripe`/`epay`), per-processor credentials, live/test toggle, hold TTL, "test webhook" button, and a "Recommended gateways: Bulgaria (ePay.bg) + Global (Stripe)" helper note.

## 9. Refund wiring (fills the real gap)

- Existing: `Ticket_Cancellation_Service` sets `_ticket_refund_status = requested` + fires `evt_ticket_refund_requested`; **nothing listens, no money moves**.
- Phase 1: `Refund_Service` listens to `evt_ticket_refund_requested` (and an organizer-initiated action), maps ticket → its `evt_order` (via `_evt_order_ticket_ids`), calls `processor->refund(transaction_id, order_amount, currency)` (full order), on success marks order `evt_refunded`, sets `_ticket_refund_status = refunded` on the order's tickets, emails attendee. Failure → sets `_ticket_refund_status = declined` + admin notice.
- Full-order-only enforced in Phase 1 (ledger `_evt_order_refunded_amount` starts 0; partial refunds = Phase 2 arithmetic on the same field). If a user requests refund for one ticket of a multi-ticket order, Phase 1 treats it as full-order refund of the whole order and documents that.

## 10. Compliance notes (merchant-facing, not plugin code)

- **In-person event admission**: special place-of-supply rule → VAT due where the event takes place; OSS is largely irrelevant for B2C in-person tickets (earlier framing corrected).
- **Bulgaria — Наредба Н-18**: fiscal receipt obligations for online card payments. ePay.bg / myPOS as licensed PSPs may qualify merchants for the fiscal-device exemption; the Stripe path is less clear-cut. This is an accountant/legal question, **documented for merchants**, not implemented.
- **Stripe availability**: BG supported since 2020 (2026 eurozone entry only switched BGN→EUR). No design impact; EUR is the Phase 1 currency.

## 11. File map

**New**
- `includes/class-cpt-orders.php` — `evt_order` CPT + custom statuses.
- `includes/class-payment-service.php` — facade replacing `Plugin::payments()` for the direct path; processor registry; create-order/reserve/session orchestration.
- `includes/payments/interface-payment-processor.php`
- `includes/payments/class-stripe-processor.php`
- `includes/payments/class-epay-processor.php`
- `includes/payments/class-payment-order-service.php` — order CRUD + status transitions.
- `includes/payments/class-reservation-service.php` — hold counters, TTL, sweep, `Event_Lock` guards.
- `includes/payments/class-refund-service.php` — listens to refund actions, calls processor, updates order+tickets.
- `includes/payments/class-ticket-issuance-service.php` — single source of truth for issuance (extracted from the paid + free paths).
- `includes/payments/class-payment-webhook-controller.php` — `register_rest_route` + idempotency.
- `includes/payments/class-payment-order-status-controller.php` — public return-page status.
- `includes/payments/class-order-record.php` — lightweight value object passed to processors.
- `docs/modules/09-paid-ticketing-merchant-guide.md` — gateway setup, N-18 note, cache/WAF exclusions.

**Modified**
- `includes/class-plugin.php` — instantiate services; `Plugin::payment_service()`; WooCommerce/Google Wallet wiring removed.
- `includes/class-ticket-box-ajax.php` — paid branch → direct path; dedupe free-path issuance onto `Ticket_Issuance_Service`; add return-page polling response.
- `includes/class-settings-store.php` — `secret` type + processors section.
- `includes/admin/class-settings-page.php` — render `secret` field.
- `includes/admin/class-payment-processor-settings.php` — processor status block (replaces the former WooCommerce status block).
- `uninstall.php` — delete `evt_order` posts/meta + caps cleanup.
- `includes/requirements.php` — require new files.

**Removed**
- `includes/class-woocommerce-checkout-service.php`, `includes/class-woocommerce-order-ticketing.php`, `includes/admin/class-woocommerce-settings.php`, `includes/class-google-wallet-service.php`.

## 12. Phasing

- **Phase 1 (shipped)**: `evt_order`, reservation holds, `Ticket_Issuance_Service` extraction, Stripe + ePay processors, webhook + return-page polling, secrets field, full-order refunds. WooCommerce and Google Wallet removed from the codebase.
- **Phase 2**: myPOS processor (RSA verify), per-ticket partial refunds (same ledger), multi-currency, order reconciliation notes in admin, myPOS/other BG scheme expansion.
- **Phase 3**: platform mode — per-merchant onboarding (Stripe Connect-style) + payouts on the same `Payment_Processor` seam.

## 13. Decisions (resolved)

1. **Uninstall behavior**: `evt_order` posts + meta are deleted on uninstall (transactional data). — **implemented**.
2. **Hold TTL default**: 30 minutes, configurable via `payment_hold_ttl_minutes`. — **implemented**.
3. **Full-order-only refunds** accepted for Phase 1 (per-ticket partials are a Phase 2 item on the same ledger). — **implemented**.
4. **ePay.bg merchant onboarding** (account needed per site) — accepted; production IPN URL must be set by ePay's merchant team via email. — **documented** in the merchant guide.
5. **Exclusivity policy** — enforced at checkout-begin **and** at issuance (single rule in `Ticket_Issuance_Service`). — **implemented**.
6. **WooCommerce removed** — the WC bridge, product mapping, and paid-ticket flow were removed; paid tickets are direct-processor only. Google Wallet (which was disabled) was removed too.
