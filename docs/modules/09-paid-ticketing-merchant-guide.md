# Module: Paid Ticketing — Merchant Guide

This guide is written for site owners who are **not developers**. It explains how to switch on paid tickets, connect a payment processor, and start selling in minutes. It covers the direct checkout path (Stripe + ePay.bg) with a global and Bulgaria focus.

---

## 1. Overview

Paid events now use a **secure hosted payment page**. Your visitor pays on the payment processor's own page (Stripe or ePay.bg); the plugin **never sees or stores card details**. That keeps the plugin in the lowest PCI compliance category (PCI SAQ-A).

Two processors are available in Phase 1:

- **Stripe — Global cards** — international cards plus Apple Pay / Google Pay.
- **ePay.bg — Bulgaria** — Bulgarian bank cards, backed by BORICA.

> myPOS is coming in Phase 2.

---

## 2. How paid tickets work

The flow for a paid event is:

1. The attendee submits the Ticket Box.
2. A payment order is created and the seats are **held** (default 30 minutes).
3. The buyer pays on the hosted payment page.
4. Tickets are issued **by email as soon as payment is confirmed**.

**What the seat hold means for your visitors:** "Your seat is reserved while you pay; if you don't finish, it is released." This prevents a visitor from grabbing seats and then abandoning checkout while everyone else sees the event as sold out.

**What the attendee sees on the return page:** after paying, the attendee is sent back to your site. If ticket issuance lags the redirect for a moment, the return page shows "payment received — tickets are on their way" (or "Seat held — payment received, tickets will arrive shortly") and keeps checking until the tickets are sent.

---

## 3. Setup — Stripe (global)

1. **Create a Stripe account.** Stripe supports Bulgarian-registered businesses, so you can onboard with a BG company.
2. **Get your keys** from the Stripe dashboard:
   - the **Secret key** (a live key and a separate test key),
   - the **Webhook signing secret** — the `whsec_...` value.
3. **Open the Payments settings:** Admin → Event Tickets → Settings → Payments.
4. **Set Active Processor = "Stripe — Global cards"**.
5. **Paste the keys** into the matching fields:
   - **Stripe Mode** — `Live` or `Test` (start with `Test` to try a payment end-to-end, then switch to `Live`).
   - **Stripe Secret Key (live)** and **Stripe Secret Key (test)**.
   - **Stripe Webhook Secret (live)** and **Stripe Webhook Secret (test)**.
6. **Configure the webhook in the Stripe dashboard** — point a webhook endpoint at:

   ```
   <site>/wp-json/evt/v1/payments/stripe/webhook
   ```

   and subscribe to the event **`checkout.session.completed`**.

Stripe cards are processed in **EUR**, which is the default currency (see section 5). Test mode uses Stripe's test cards and never moves real money.

---

## 4. Setup — ePay.bg (Bulgaria)

1. **Create an ePay.bg merchant account** — this is a merchant contract with ePay / BORICA acquiring.
2. **Get your credentials** from ePay.bg:
   - the **Merchant ID (MIN)**,
   - the **Secret**.
3. **Open the Payments settings:** Admin → Event Tickets → Settings → Payments.
4. **Set Active Processor = "ePay.bg — Bulgaria"**.
5. **Enter the credentials:**
   - **ePay.bg Merchant ID (MIN)**,
   - **ePay.bg Secret**.
6. **Optionally enable ePay.bg Demo mode** while you test (in demo you can set the notification URL yourself).
7. **Register the IPN (notification) URL with ePay.bg** so the plugin receives payment confirmations:

   ```
   <site>/wp-json/evt/v1/payments/epay/webhook
   ```

   In **production** ePay sets this address for you: email your **CIN (Merchant ID)** and this URL to **merchant@epay.bg** (Commercial Department). You cannot change it yourself from the ePay profile.

> **Note on ePay.bg** — the plugin implements the official ePay WEB API: the payment request is base64-encoded with an HMAC-SHA1 checksum, and the IPN carries no amount, so the amount is checked against the stored order. Verify a payment in **Demo mode** before going live.

**Refunds (Phase 1):** ePay.bg refunds are initiated from the **ePay merchant panel** — the plugin then detects the refunded status and marks the order and its tickets as refunded. See section 9.

---

## 5. Currency

The default currency is **EUR**. Bulgaria is in the eurozone since January 2026, so EUR is the natural choice for both the global (Stripe) and the Bulgarian (ePay.bg) paths.

- To change it, use the **Currency** field under Admin → Event Tickets → Settings → Payments.
- The currency is stored per order, so the setting can be extended later — but Phase 1 charges everything in the currency you set here.

---

## 6. Seat hold setting

- The hold is controlled by **Seat Hold (minutes)** under Admin → Event Tickets → Settings → Payments (default **30**).
- The value is how long a pending payment keeps seats reserved before they are released.

**What happens when the hold expires:**

- The order is marked **Expired** and the seats are released back to the pool.
- If a payment still arrives after expiry (for example the webhook was slow), the plugin **auto-refunds** it rather than issuing a ticket for a seat that is already gone. The attendee is told the order expired and the refund was initiated.

---

## 7. Cache / firewall / hosting exclusions (IMPORTANT for webhooks)

Payment notifications are plain **POST requests** sent by the processor to:

```
<site>/wp-json/evt/v1/payments/<processor>/webhook
```

For example, `.../payments/stripe/webhook` and `.../payments/epay/webhook`. Because these arrive automatically (not from a browser click), your hosting setup must let them through:

- **Page caching:** make sure caching plugins do **not** cache POST requests to `/wp-json/`.
- **Web Application Firewall / Cloudflare:** exclude `/wp-json/evt/v1/payments/` from any WAF rules or managed challenge rules.
- **Bot protection:** disable bot protection (e.g. Cloudflare Bot Fight Mode, challenge rules) for those endpoints.
- **REST API:** keep the REST endpoint reachable — do not use "disable REST API" plugins.

> **If these are blocked, paid orders will not complete even though the customer was charged.** The money leaves the buyer's card but the plugin never hears about it. If orders ever get stuck on Pending, check this list first.

---

## 8. Bulgaria compliance note (merchant must verify with an accountant)

- **VAT on in-person event admission:** for events held in person, VAT is generally due **where the event takes place** (the special place-of-supply rule). For in-person tickets, OSS is therefore largely irrelevant.
- **Bulgarian fiscal-device obligations:** Bulgarian merchants must check their obligations under **Наредба Н-18** for online card payments. Using licensed payment service providers such as **ePay.bg** or **myPOS** may qualify for the fiscal-device exemption; the **Stripe** path is less clear-cut.

This is **not legal advice** — verify your situation with a local accountant before launch.

---

## 9. Refunds & cancellations (merchant view)

Phase 1 supports **full-order refunds only**. Per-ticket partial refunds arrive in Phase 2.

- **Stripe:** refunds are processed by the plugin automatically — the money is moved and the order and tickets are marked refunded.
- **ePay.bg:** refunds are initiated from the **ePay merchant panel**; the plugin picks up the refunded status and marks the order and tickets accordingly.

If a buyer requests a refund for one ticket of a multi-ticket order in Phase 1, it is treated as a full-order refund of the whole order.

---

## 10. Troubleshooting

| Symptom | Likely cause | Quick fix |
| --- | --- | --- |
| Order stuck on **Pending** | Webhook blocked (cache / WAF / bot protection), or the signing secret doesn't match | Check section 7 exclusions; re-check the webhook signing secret in the processor dashboard and in Payments settings |
| Paid, but no ticket email | Order may not show Paid yet, or the email landed in spam | Check the order shows **Paid**; check the spam folder; resend the tickets from the Tickets admin |
| "Sold out" during checkout | Another buyer's seat hold hasn't expired yet | The hold is released automatically (default 30 min) — ask the buyer to try again shortly |

---

# Резюме на български

- Платените събития вече ползват **сигурна хостинг страница за плащане** — плъгинът никога не докосва данните на картата (PCI SAQ-A).
- **Два процесора** в Phase 1: **Stripe** (международни карти, Apple/Google Pay) и **ePay.bg** (български банкови карти, BORICA). **myPOS** идва в Phase 2.
- Настройки: **Admin → Event Tickets → Settings → Payments** — поле **Active Processor**, ключове за Stripe (**Stripe Mode**, **Secret Key**, **Webhook Secret** — `whsec_...`) и за ePay.bg (**Merchant ID (MIN)**, **Secret**, **Demo mode**).
- **Webhook URL-и:** Stripe → `<site>/wp-json/evt/v1/payments/stripe/webhook`; ePay.bg IPN → `<site>/wp-json/evt/v1/payments/epay/webhook`.
- **Местата се задържат** при започване на плащането (по подразбиране 30 мин); при изтичане поръчката става **Expired**, местата се освобождават, а закъсняло плащане се **възстановява автоматично**.
- **Валута по подразбиране: EUR** (България е в еврозоната от януари 2026 г.).
- **Внимание с кеша и защитните стени:** POST заявките към `/wp-json/evt/v1/payments/` не трябва да се кешират или блокират — иначе платени поръчки няма да завършват, въпреки че клиентът е платил.
- **Данъци:** за присъствени събития ДДС обикновено се дължи по мястото на провеждане; проверете задълженията по **Наредба Н-18** (лицензирани PSP като ePay.bg/myPOS може да освобождават от фискално устройство). Не е правен съвет — консултирайте се със счетоводител преди старт.
