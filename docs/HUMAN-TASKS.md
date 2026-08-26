# Tasks that need a human

Everything in the plugin is written and self-consistent. The items below are the
ones that **cannot** be done from code — they need credentials, a real Mercado
Pago account, a real domain, or a judgement call. They are ordered: nothing
below a heading works until everything above it is done.

## P0 — Blocking. The plugin cannot take a single payment without these.

### 1. Mercado Pago application and credentials

- [ ] Create (or identify) the application in the Mercado Pago developer
      dashboard for the country you collect in.
- [ ] Copy the **production** access token and public key into
      *Site administration ▸ Plugins ▸ Enrolments ▸ Mercado Pago Checkout Pro*,
      or better, into `config.php` / environment variables.
- [ ] Copy the **test** access token and public key into the test fields.
- [ ] Copy the **secret signature** from *Your integrations ▸ Webhooks* into the
      webhook secret field. This is a different value from the access token, and
      the test application has its own.

Nobody but you can do this: the credentials do not exist anywhere in this
repository and must never be committed.

### 2. HTTPS on a resolvable domain

- [ ] Confirm `$CFG->wwwroot` starts with `https://` and resolves publicly.

Mercado Pago rejects `localhost`, `127.0.0.1` and plain http for
`notification_url` and `back_urls`. The plugin refuses to be enabled otherwise.
For development, tunnel the site and set `$CFG->wwwroot` to the tunnel URL.

### 3. Register the webhook URL

- [ ] In *Your integrations ▸ Webhooks*, register
      `https://your-site/enrol/mercadopagocpro/webhook.php` and subscribe to the
      **Payments** topic (and Merchant orders if you want them).
- [ ] Verify from outside your network that the URL is reachable and is not
      behind basic auth, an IP allowlist, or a WAF rule that drops JSON POSTs:

      curl -i -X POST https://your-site/enrol/mercadopagocpro/webhook.php -d '{}'

      A `401` is the correct answer — it proves the request arrives.

### 4. Moodle cron

- [ ] Confirm cron runs at least every minute and that
      `\enrol_mercadopagocpro\task\reconcile_payments` appears in the task list
      and succeeds.

This is the safety net that settles payments when a notification is lost. A site
with broken cron will take money and enrol nobody.

### 5. Enable the plugin

- [ ] *Site administration ▸ Plugins ▸ Enrolments ▸ Manage enrol plugins* ▸
      enable **Mercado Pago Checkout Pro**.

## P1 — Required before real money moves.

### 6. End-to-end test run

Run the scenarios in [`TESTING.md`](TESTING.md) against the **test** environment
with test users and test cards. At minimum:

- [ ] `APRO` → approved → enrolled, notified, visible in the report.
- [ ] `CONT` → pending → holding enrolment (if enabled) → activates on approval.
- [ ] `FUND` → rejected → not enrolled, can retry.
- [ ] Refund from the dashboard → enrolment suspended within one reconciliation cycle.
- [ ] Webhook disabled → reconciliation still enrols the buyer.

Automated tests prove the plugin's logic; only this proves your account is
configured correctly.

### 7. Business decisions someone has to make

These have defaults, but the defaults are guesses about your institution:

- [ ] **Action on refund or chargeback** — suspend (default), unenrol, or keep.
      Suspending preserves grades and lets you reinstate; unenrolling does not.
- [ ] **Holding enrolments for pending payments** — off by default. Turn it on
      if you sell to buyers who pay with cash coupons or bank transfer and you
      want teachers to see them coming.
- [ ] **Enrolment duration and expiry action** — how long access lasts and what
      happens when it ends.
- [ ] **Installments** — how many, and whether to preselect one. More
      installments means a higher Mercado Pago commission for you.
- [ ] **Excluded payment types** — excluding cash coupons removes the pending
      state entirely but loses unbanked buyers.
- [ ] **Statement descriptor** — what buyers see on their card statement.
      Getting this wrong is a common cause of chargebacks.
- [ ] **Course welcome message** — off by default. Decide whether to send one,
      who it should come from, and whether to write your own text or use the core
      default. Remember it goes out only when the payment is approved.
- [ ] **Who gets `enrol/mercadopagocpro:viewtransactions`** — the report shows who
      paid what. Editing teachers have it by default; decide if that is right.

### 8. Refund policy and the first production payment

- [ ] Take one small **real** payment end to end, confirm the enrolment, then
      refund it and confirm the reversal is picked up.
- [ ] Write down who is authorised to issue refunds in the Mercado Pago
      dashboard, and make sure they know that refunding there is what drives the
      enrolment change in Moodle.

## P2 — Only if you use split payments.

- [ ] Confirm your account meets the [split payments prerequisites](https://www.mercadopago.com.br/developers/en/docs/split-payments/prerequisites)
      (seller account with the required KYC level).
- [ ] Register `https://your-site/enrol/mercadopagocpro/oauth.php` as the
      redirect URI of your marketplace application — it must match exactly.
- [ ] Put the application client id and client secret into the plugin settings.
- [ ] Connect one seller through the enrolment method settings and confirm the
      seller id is filled in.
- [ ] Run a test payment and confirm the money and the commission land in the
      right accounts. `marketplace_fee` is an absolute amount, not a percentage.
- [ ] Decide how seller tokens get refreshed. `oauth_helper::refresh()` exists
      and works, but nothing calls it on a schedule yet — tokens from the
      authorization-code flow last 180 days, so plan for a reconnection or wire
      the refresh into a task before then.

## P3 — Operational, worth doing in the first month.

- [ ] Back up `$CFG->dataroot/secret/` if you use per-course credentials.
      Without that key they are unreadable.
- [ ] Set up monitoring for: failed `reconcile_payments` runs, a growing pile of
      `pending` transactions, a steady stream of `webhook_rejected` events, and
      any transaction with `status = approved` and `enrolmentstate = none`.
      That last one should never happen.
- [ ] Decide a retention period for abandoned checkouts and webhook logs
      (default 180 days) against your local record-keeping rules.
- [ ] Review the privacy statement: the plugin sends the buyer's name and email
      to Mercado Pago. Confirm that is covered by your privacy policy and, in
      the EU/UK, your Article 30 record.
- [ ] Add the plugin's Spanish strings to your language customisation if you use
      a regional Spanish pack (the bundled pack uses Rioplatense forms).

## Known gaps, deliberately left for a human

- **Partial refunds.** Mercado Pago reports a partial refund as `refunded` on
  the payment. The plugin treats any `refunded` as a full reversal. If you
  routinely refund part of a fee, set the reversal action to *Keep the
  enrolment* and handle access manually.
- **Seller token refresh is not scheduled.** See P2.
- **Currency list.** The plugin offers ARS, BRL, CLP, COP, MXN, PEN and UYU. If
  you collect in a Mercado Pago site not in that list, add it to
  `util::supported_currencies()` after checking the account actually supports it.
- **Behat stops at the redirect.** The Mercado Pago checkout itself is out of
  reach for automated browser tests; the manual scenarios cover it.
