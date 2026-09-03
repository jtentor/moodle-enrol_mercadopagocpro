# What the administrator must configure

This plugin is installed and working the moment Moodle finishes the upgrade. It will not
take a single payment until you have done everything in P0, because every item there is a
credential, a domain or a judgement call that only the holder of the Mercado Pago account
can supply.

Sections are ordered. Nothing below a heading works until everything above it is done.

Setting names below match *Site administration ▸ Plugins ▸ Enrolments ▸ Mercado Pago
Checkout Pro* exactly.

---

## P0 — Blocking. No payment can be taken without these.

### 1. Mercado Pago application and credentials

- [ ] Create, or identify, the application in the Mercado Pago developer dashboard for the
      country you collect in.
- [ ] Set **Environment** (`environment`) to production or test.
- [ ] Fill **Access token**, **Public key** and **Webhook secret**
      (`accesstoken`, `publickey`, `webhooksecret`), or put them in `config.php`.
- [ ] Fill the test triplet as well if you intend to test:
      `testaccesstoken`, `testpublickey`, `testwebhooksecret`.

The webhook secret is a different value from the access token, taken from *Your
integrations ▸ Webhooks*, and **the test application has its own**. Using the production
secret with test credentials produces signature failures that look like an attack and are
not.

**One application per plugin.** The notification URL belongs to the application, not to an
individual payment, so two Mercado Pago plugins pointed at the same application receive
each other's notifications. If this site runs more than one, create a second application.

**Where the credentials live, and which copy wins.** Server configuration —
`config.php` or the environment — **outranks** the site settings:

```php
$CFG->enrol_mercadopagocpro = [
    'accesstoken'   => '...',
    'publickey'     => '...',
    'webhooksecret' => '...',
];
```

If you set a token there and later change it through the web interface, the web interface
will appear to accept the change and the old token will keep being used. When a credential
change has no effect, look in `config.php` first. Per-instance credentials are a third
source, available only if **Allow per-instance credentials**
(`allowinstancecredentials`) is on.

Nobody but you can do this. The credentials exist nowhere in this repository and must never
be committed to one.

### 2. HTTPS on a resolvable domain

- [ ] Confirm `$CFG->wwwroot` starts with `https://` and resolves publicly.

Mercado Pago rejects `localhost`, `127.0.0.1` and plain HTTP for `notification_url` and
`back_urls`. The plugin refuses to enable an instance otherwise, and the refusal is
deliberate rather than a defect. For development, tunnel the site and point
`$CFG->wwwroot` at the tunnel URL.

### 3. Register the webhook URL

- [ ] In *Your integrations ▸ Webhooks*, register
      `https://your-site/enrol/mercadopagocpro/webhook.php` and subscribe to the
      **Payments** topic, plus Merchant orders if you want them.
- [ ] Register it for the test environment too, if you will be testing.
- [ ] Verify from outside your network that the URL is reachable and not behind basic
      auth, an IP allowlist or a WAF rule that drops JSON POSTs:

      curl -i -X POST https://your-site/enrol/mercadopagocpro/webhook.php -d '{}'

      A `401` is the correct answer. It proves the request arrives.

The plugin cannot register this for you: `notification_url` sent per payment is accepted by
the API and silently discarded, so the application settings are the only place it takes
effect.

### 4. Moodle cron

- [ ] Confirm cron runs at least every minute, and that
      `\enrol_mercadopagocpro\task\reconcile_payments` appears in the task list and
      succeeds.

This is the safety net that settles a payment when a notification is lost. **A site with
broken cron will take money and enrol nobody.**

### 5. Enable the plugin

- [ ] *Site administration ▸ Plugins ▸ Enrolments ▸ Manage enrol plugins* ▸ enable
      **Mercado Pago Checkout Pro (Tentor & Associates)**.

If the method still does not appear in a course's *Add method* dropdown:

```bash
sudo -u www-data php enrol/mercadopagocpro/cli/diagnose.php
```

It checks installation, class loading, orphan rows from earlier builds, capabilities,
HTTPS, the SDK, the database tables, credentials and the scheduled tasks, and names the one
that is the reason.

---

## P1 — Required before real money moves.

### 6. End-to-end test run

Run the scenarios in [`TESTING.md`](TESTING.md) against the **test** environment with test
users and test cards. At minimum:

- [ ] `APRO` → approved → enrolled, notified, visible in the report.
- [ ] `CONT` → pending → holding enrolment, if enabled → activates on approval.
- [ ] `FUND` → rejected → not enrolled, can retry.
- [ ] Refund from the dashboard → enrolment suspended within one reconciliation cycle.
- [ ] Webhook disabled → reconciliation still enrols the buyer.

Three things about test mode that are not obvious, each of which looks like a plugin defect
when it bites:

- **The buyer must already be logged in to Mercado Pago before starting the purchase.**
  Otherwise the checkout falls back to guest mode — card entry only, no account money, no
  saved cards. The working order, in a private window: sign in to Mercado Pago as the test
  buyer, then open Moodle in the same window and log in as the student, then press pay.
- **Test mode is a matter of credentials and buyer account, not a different URL.** There is
  a `sandbox_init_point` field in the API response; it is legacy, and redirecting to it
  produces a redirect loop. The plugin always uses `init_point`.
- **A real collector cannot be paid by a test buyer, and a test collector cannot be paid by
  a real one.** Nothing warns you when the checkout is created; it renders normally and
  fails at the last step.

The automated tests prove the plugin's logic. Only this run proves your account is
configured correctly.

### 7. Business decisions someone has to make

These have defaults, and the defaults are guesses about your institution.

- [ ] **Action on refund or chargeback** (`reversalaction`) — suspend (default), unenrol,
      or keep. Suspending preserves grades and lets you reinstate; unenrolling does not.
- [ ] **Holding enrolments for pending payments** (`pendingholding`) — off by default. Turn
      it on if you sell to buyers who pay with cash coupons or bank transfer and you want
      teachers to see them coming.
- [ ] **Enrolment duration and expiry action** (`enrolperiod`, `expiredaction`) — how long
      access lasts and what happens when it ends.
- [ ] **Installments** (`installments`, `defaultinstallments`) — how many, and whether to
      preselect one. More installments means a higher Mercado Pago commission for you.
- [ ] **Excluded payment types** (`excludedpaymenttypes`) — excluding cash coupons removes
      the pending state entirely but loses unbanked buyers.
- [ ] **Require a Mercado Pago account** (`walletpurchase`) — off by default. On, the
      checkout accepts only authenticated Mercado Pago accounts: no guests, no cash
      coupons, no bank transfer. Sometimes exactly what you want, sometimes a large
      fraction of your prospective students.
- [ ] **Statement descriptor** (`statementdescriptor`) — what buyers see on their card
      statement. Getting this wrong is a common cause of chargebacks.
- [ ] **Course welcome message** (`sendcoursewelcomemessage`) — off by default. Decide
      whether to send one, who it comes from, and whether to write your own text. It goes
      out only when the payment is approved, never on a pending enrolment. **If your
      template is bilingual, enable the multilang filter**, or students receive both
      languages one after the other.
- [ ] **Who holds `enrol/mercadopagocpro:viewtransactions`** — the report shows who paid
      what. `manager` and `editingteacher` have it by default; decide whether that is right
      for your institution. `enrol/mercadopagocpro:manage` is also granted to
      `editingteacher`, while `:config` — which sets the price a course charges — is
      `manager` only. `db/access.php` documents how to change any of them.

### 8. Refund policy and the first production payment

- [ ] Take one small **real** payment end to end, confirm the enrolment, then refund it and
      confirm the reversal is picked up.
- [ ] Write down who is authorised to issue refunds in the Mercado Pago dashboard, and make
      sure they know that refunding there is what drives the enrolment change in Moodle.

---

## P2 — Only if you use split payments.

- [ ] Confirm your account meets the [split payments
      prerequisites](https://www.mercadopago.com.br/developers/en/docs/split-payments/prerequisites)
      — a seller account with the required KYC level.
- [ ] Register `https://your-site/enrol/mercadopagocpro/oauth.php` as the redirect URI of
      your marketplace application. It must match exactly.
- [ ] Put the application client id and secret into `marketplaceclientid` and
      `marketplaceclientsecret`, and turn on `marketplaceenabled`.
- [ ] Connect one seller through the enrolment method settings and confirm the seller id is
      filled in.
- [ ] Run a test payment and confirm the money and the commission land in the right
      accounts. `marketplace_fee` is an absolute amount, not a percentage.
- [ ] **Plan for token expiry.** Tokens from the authorization-code flow last 180 days.
      Reconnecting the seller before then is a calendar entry someone has to own.

---

## P3 — Operational, worth doing in the first month.

- [ ] Back up `$CFG->dataroot/secret/` if you use per-instance credentials. Without that
      key they are unreadable.
- [ ] Set up monitoring for: failed `reconcile_payments` runs, a growing pile of `pending`
      transactions, a steady stream of `webhook_rejected` events, and any transaction with
      `status = approved` and `enrolmentstate = none`. That last one should never happen.
- [ ] Decide a retention period for abandoned checkouts and webhook logs (`cleanupafter`,
      default 180 days) against your local record-keeping rules.
- [ ] Review your privacy statement. The plugin sends the buyer's name and email to Mercado
      Pago. Confirm that is covered, and in the EU or UK that it appears in your Article 30
      record.

---

## Limits you should hear from us rather than discover

- **Partial refunds are treated as full reversals.** Mercado Pago reports a partial refund
  as `refunded` on the payment, with nothing distinguishing it. If you routinely refund
  part of a fee, set `reversalaction` to *keep the enrolment* and manage access by hand.
- **The currency list is fixed**: ARS, BRL, CLP, COP, MXN, PEN, UYU. If you collect on a
  Mercado Pago site outside that list, it must be added to `util::supported_currencies()`
  after confirming the account supports it.
- **The guest payment path cannot be exercised in a test environment at all.** A test
  collector accepts only test payers; a real collector accepts guests but no payment
  completes against a test buyer. There is no combination of accounts in which an
  unregistered payer completes a purchase in test mode, so that path can only be validated
  in production with real money. This is a property of the Mercado Pago platform.
- **Mercado Pago runs its own retry cycle** on a failed charge, independently of anything
  Moodle does. A first failure is not final.
- **Automated browser tests stop at the redirect.** Once the buyer reaches Mercado Pago the
  checkout is out of reach; the manual scenarios in `TESTING.md` cover it.

---

## Where to look when something is wrong

| Symptom | Start at |
| --- | --- |
| Method missing from the *Add method* dropdown | `cli/diagnose.php` |
| Payment taken, no enrolment | `docs/TROUBLESHOOTING.md`, then the transactions report |
| Webhook signature failures | §1 — production versus test secret |
| A credential change has no effect | §1 — `config.php` outranks the site settings |
| Both languages in the welcome email | §7 — multilang filter |
| `approved` transaction with `enrolmentstate = none` | P3 — this should never happen; open an issue |

Full production checklist: [`DEPLOYMENT.md`](DEPLOYMENT.md). Testing procedures:
[`TESTING.md`](TESTING.md). Every Mercado Pago endpoint and field the plugin uses, with its
source in the official documentation: [`API.md`](API.md).
