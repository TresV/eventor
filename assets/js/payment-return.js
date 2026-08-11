/**
 * Payment return-page status polling for the direct paid checkout path
 * (Stripe / ePay.bg).
 *
 * When the buyer is redirected back to the site with `?evt_order=<public_key>`
 * (Stripe success_url or ePay URL_OK), this script shows a lightweight status
 * bar and polls `GET evt/v1/orders/<public_key>` until the payment is
 * confirmed (or times out). It never renders PII — only status + a friendly
 * message.
 *
 * Localized via `EvtPaymentReturn` (pollUrl + i18n). No jQuery dependency so
 * it can run on any front-end page, including one without the Ticket Box.
 */
(function () {
    'use strict';

    function getParam(name) {
        const m = new RegExp('[?&]' + name + '=([^&]+)').exec(window.location.search);
        return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
    }

    const orderKey = getParam('evt_order');
    if (!orderKey) {
        return;
    }

    const cfg = (typeof EvtPaymentReturn === 'object' && EvtPaymentReturn) ? EvtPaymentReturn : {};
    const pollUrl = cfg.pollUrl || '';
    if (!pollUrl) {
        return;
    }

    const i18n = Object.assign({
        pending: 'Payment received — your seats are held. Tickets are being prepared…',
        paid: 'Payment confirmed — tickets are on their way to your inbox.',
        failed: 'Payment was not completed. Your seats have been released.',
        timeout: 'Still confirming your payment — tickets will arrive by email shortly.',
    }, (cfg.i18n || {}));

    const STATUS_PAID = 'evt_paid';
    const STATUS_FAILED = 'evt_failed';
    const STATUS_EXPIRED = 'evt_expired';
    const STATUS_REFUNDED = 'evt_refunded';
    const POLL_INTERVAL = 3000;
    const MAX_ATTEMPTS = 20; // ~60 seconds

    // Non-intrusive, fixed status bar pinned to the top of the page.
    const bar = document.createElement('div');
    bar.id = 'evt-payment-return-bar';
    bar.setAttribute('role', 'status');
    bar.setAttribute('aria-live', 'polite');

    const text = document.createElement('span');
    text.className = 'evt-payment-return-bar__text';
    bar.appendChild(text);
    document.body.insertBefore(bar, document.body.firstChild);

    const style = document.createElement('style');
    style.textContent =
        '#evt-payment-return-bar{position:fixed;top:0;left:0;right:0;z-index:99999;' +
        'padding:12px 16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
        'font-size:14px;line-height:1.4;text-align:center;color:#1f2937;background:#eff6ff;' +
        'border-bottom:1px solid #bfdbfe;}' +
        '#evt-payment-return-bar.is-success{background:#ecfdf5;border-color:#bbf7d0;color:#166534;}' +
        '#evt-payment-return-bar.is-error{background:#fef2f2;border-color:#fecaca;color:#991b1b;}';
    document.head.appendChild(style);

    function render(state, message) {
        bar.className = 'evt-payment-return-bar' + (state ? ' ' + state : '');
        text.textContent = message;
    }

    function finish(ok, message) {
        render(ok ? 'is-success' : 'is-error', message);
    }

    let attempts = 0;

    function poll() {
        if (attempts >= MAX_ATTEMPTS) {
            finish(false, i18n.timeout);
            return;
        }
        attempts++;

        fetch(pollUrl, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json();
            })
            .then(function (json) {
                const status = (json && json.status) || '';
                if (status === STATUS_PAID) {
                    finish(true, i18n.paid);
                    return;
                }
                if (status === STATUS_FAILED || status === STATUS_EXPIRED || status === STATUS_REFUNDED) {
                    finish(false, i18n.failed);
                    return;
                }
                render('', i18n.pending);
                window.setTimeout(poll, POLL_INTERVAL);
            })
            .catch(function () {
                // Transient error (e.g. REST still warming up) — keep polling.
                render('', i18n.pending);
                window.setTimeout(poll, POLL_INTERVAL);
            });
    }

    render('', i18n.pending);
    poll();
})();
