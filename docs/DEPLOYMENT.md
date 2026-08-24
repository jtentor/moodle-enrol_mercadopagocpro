# Deployment

## Requirements

| | |
| --- | --- |
| Moodle | 5.2.2 (`2026042002.00`) or a later 5.2 release |
| PHP | 8.2 or later (the Mercado Pago SDK requires it) |
| PHP extensions | `curl`, `json`, `openssl`, `sodium` (for credential encryption), `intl` (recommended, for currency formatting) |
| Transport | HTTPS with a publicly resolvable domain name |
| Outbound | `https://api.mercadopago.com` and, for split payments, `https://auth.mercadopago.com` |
| Cron | Moodle cron running at least every minute |

`localhost` and bare IP addresses are rejected by Mercado Pago for
`notification_url` and `back_urls`.

## Coming from `enrol_mp_checkoutpro`

Uninstall the old plugin from *Plugins overview ▸ Uninstall* (export its
transaction report first — uninstalling drops its tables), delete
`enrol/mp_checkoutpro` from disk, then install this one. Note that the config.php
key changes with the component name, from `$CFG->enrol_mp_checkoutpro` to
`$CFG->enrol_mpcheckoutpro`. The `MPCHECKOUTPRO_*` environment variable names
are unchanged, so a server already exporting them needs no edits.

## Install

```bash
cd /path/to/moodle
# Moodle 5.x source trees keep code under public/
git clone <repo> public/enrol/mpcheckoutpro
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Then enable it:

```bash
php admin/cli/cfg.php --name=enrol_plugins_enabled --set="manual,guest,self,cohort,mpcheckoutpro"
```

or through **Site administration ▸ Plugins ▸ Enrolments ▸ Manage enrol plugins**.

### The SDK

The official Mercado Pago PHP SDK ships inside `vendor/mercadopago`. Nothing
else is needed. To manage it with Composer instead:

```bash
cd public/enrol/mpcheckoutpro
composer install --no-dev --optimize-autoloader
```

`vendor/autoload.php` takes precedence over the bundled copy when it exists.
The plugin settings page reports which SDK version was detected.

## Configure

### 1. Credentials

Preferred, for production: keep them out of the database.

```php
// config.php, before require_once(__DIR__.'/lib/setup.php');
$CFG->enrol_mpcheckoutpro = [
    'accesstoken'   => getenv('MPCHECKOUTPRO_ACCESS_TOKEN'),
    'publickey'     => getenv('MPCHECKOUTPRO_PUBLIC_KEY'),
    'webhooksecret' => getenv('MPCHECKOUTPRO_WEBHOOK_SECRET'),
];
```

The environment variables are read directly if `$CFG->enrol_mpcheckoutpro` is
absent. Otherwise use the plugin settings page.

The **webhook secret is not the access token**: it is the separate secret
signature shown beside the webhook configuration in *Your integrations*, and it
differs between the test and production applications.

### 2. Webhook URL

Register in *Your integrations ▸ Webhooks*:

```
https://your-site.example/enrol/mpcheckoutpro/webhook.php
```

Subscribe to the **Payments** topic. Merchant orders are also handled if you
subscribe to them.

The plugin additionally sets `notification_url` on every preference it creates,
with an `enrolid` query parameter, so notifications work even before the
dashboard is configured — but registering it there is what covers events that
arrive outside a preference.

### 3. Encryption key

Per-course credentials are encrypted with `\core\encryption`, which needs a key
in `$CFG->dataroot/secret/`. The plugin creates it on first use. **Back it up
with your dataroot**: without it, stored per-course credentials are unreadable.

Sites that only use site-wide credentials do not need this.

### 4. Scheduled tasks

Four tasks are installed. The defaults are sensible; the reconciliation one is
the important one.

| Task | Default | Purpose |
| --- | --- | --- |
| `reconcile_payments` | every 10 minutes | Re-queries every non-final transaction. This is what makes a lost webhook harmless |
| `retry_webhooks` | every 5 minutes | Re-processes deferred or failed notifications |
| `process_expirations` | every minute | Standard enrolment expiry and notifications |
| `cleanup_records` | daily at 03:25 | Deletes abandoned checkouts and old log rows |

Verify cron is actually running: **Site administration ▸ Server ▸ Tasks ▸ Task
logs**. A site whose cron is broken will take payments and never enrol anyone.

### 5. Web server

The webhook endpoint must be reachable by Mercado Pago:

- Do not put it behind HTTP basic auth, an IP allowlist, or a WAF rule that
  blocks unknown POST bodies.
- Do not force a login redirect on `/enrol/mpcheckoutpro/webhook.php`.
- Allow a request body of at least 64 KB.
- Keep `mod_security` from stripping the `x-signature` and `x-request-id`
  headers.

Everything else in the plugin is a normal authenticated Moodle page.

## Hardening checklist

- [ ] HTTPS everywhere, with HSTS. The plugin refuses to be enabled otherwise.
- [ ] **Require a valid signature** left on. Turning it off means anyone who
      learns a payment id could try to trigger processing — the plugin still
      re-queries the API, so it cannot be tricked into a false enrolment, but
      it can be made to burn API calls.
- [ ] Credentials in `config.php`/environment rather than the database, and
      `config.php` not world-readable.
- [ ] **Verbose logging off**. It is redacted, but it is noisy and it records
      payment ids.
- [ ] Rate limits left at their defaults unless you have a reason.
- [ ] `enrol/mpcheckoutpro:viewtransactions` and `:reconcile` granted only to
      the people who need them — the report shows who paid what.
- [ ] Per-course credentials disabled unless you actually run multiple sellers.
- [ ] The encryption key backed up if per-course credentials are on.
- [ ] Log rotation for the server error log.

## Monitoring

Watch these:

- **Task logs** for `reconcile_payments` failures.
- The transaction report filtered to `pending` / `in_process`: a growing pile
  that never resolves means notifications are not arriving *and* reconciliation
  is failing.
- `webhook_rejected` events in the site log. A few are normal (probes); a steady
  stream means a wrong webhook secret.
- `grep enrol_mpcheckoutpro /var/log/php-fpm/error.log` for `[ERROR]` lines.

A useful early warning is a transaction with `status = approved` and
`enrolmentstate = none`: that is an approved payment that did not become an
enrolment. It should never happen; if it does, the `lasterror` column says why
(most often an amount mismatch or a full course).

## Upgrading

```bash
cd public/enrol/mpcheckoutpro && git pull
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Transactions are never deleted by an upgrade. When you replace the bundled SDK,
update the version recorded in `thirdpartylibs.xml` as well.

## Uninstalling

Uninstalling through **Manage enrol plugins ▸ Uninstall** drops the three plugin
tables, including the payment history. Export the transaction report first if
you need it for accounting.
