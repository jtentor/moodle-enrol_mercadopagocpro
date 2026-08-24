# Troubleshooting

Work from the symptom. Almost every problem shows up in one of three places: the
transaction report (**course ▸ Participants ▸ Enrolment methods ▸ the report
icon**), the site log filtered to `enrol_mpcheckoutpro`, or the server error log.

**Start here.** The plugin ships a diagnostic that checks the whole chain —
installation, class loading, enabled state, capabilities, HTTPS, the SDK, the
database tables, the credentials and the scheduled tasks — and tells you what to
do about each failure:

```bash
php enrol/mpcheckoutpro/cli/diagnose.php
php enrol/mpcheckoutpro/cli/diagnose.php --courseid=12 --username=jperez
```

## The method saves but never appears in the course

Symptom: you fill the form, press "Add method", the page returns to the enrolment
methods list without an error - and the method is not there. Repeating it just
creates more invisible rows.

Cause: `enrol_plugin::get_name()` in core derives the plugin name with
`explode('_', get_class($this))[1]`; its own comment says *"second word in class
is always enrol name, sorry, no fancy plugin names with _"*. For
`enrol_mpcheckoutpro_plugin` that returns `mp`, so every instance is written to
the `enrol` table with `enrol='mp'` - a plugin that does not exist. The insert
succeeds, which is why there is no error, but `enrol/instances.php` can no longer
map the row back to a plugin and skips it.

`classes/plugin.php` overrides `get_name()` to return `mpcheckoutpro`, which is
the complete fix: this is the only place in core that parses the name that way.
If you see this symptom, check the override is present:

```bash
php enrol/mpcheckoutpro/cli/diagnose.php
```

Section 2 reports `get_name()` and counts orphan rows. Remove the rows left
behind by earlier attempts with:

```bash
php enrol/mpcheckoutpro/cli/diagnose.php --fixorphans
```

Any enrolment plugin whose directory name contains an underscore needs this
override. It is the reason most core enrolment plugins have single-word names.

## The method does not appear in a course's "Add method" list

Run the diagnostic with `--courseid` and `--username` for the exact course and
person who cannot see it. The dropdown is built from three gates, and any one of
them silently removes the method:

1. **The plugin must be enabled** site-wide (*Manage enrol plugins*). Installed
   is not the same as enabled, and a disabled method never appears in a course.
2. **The class must be loadable.** `enrol_get_plugins()` skips a plugin whose
   `enrol_mpcheckoutpro_plugin` class cannot be autoloaded — with no error
   anywhere. A stale class map after copying the files in is the usual cause:
   `php admin/cli/purge_caches.php`.
3. **`enrol/mpcheckoutpro:config` must be allowed** in the course. It is granted
   to **Manager only** by default, exactly like `enrol/fee`. An editing teacher
   can add *Self enrolment* but not this one. Either use a Manager account, or
   allow the capability for the editing teacher role in *Site administration ▸
   Users ▸ Permissions ▸ Define roles*.

`moodle/course:enrolconfig` is also required, but a teacher who can add any
enrolment method already has it.

Note that HTTPS and credentials are **not** gates for adding the method — they
block *enabling* the instance, and the form says so when you save.

## The enrolment method cannot be enabled

**"No Mercado Pago credentials are configured"**
The access token is empty at every level. Check the plugin settings, then
`$CFG->enrol_mpcheckoutpro`, then the environment variables. Remember the
**Environment** switch: in *Test* mode the plugin reads the *test* access token,
not the production one.

**"Mercado Pago requires HTTPS"**
`$CFG->wwwroot` does not start with `https://`. Fix the URL, not the check —
Mercado Pago will reject `http` `back_urls` anyway.

**"The Mercado Pago PHP SDK is not available"**
`vendor/mercadopago/src/MercadoPago` is missing, usually because the directory
was excluded when the plugin was copied or a `.gitignore` swallowed `vendor/`.
Restore it, or run `composer install --no-dev` in the plugin directory. The
settings page shows the detected version when it is found.

**"The enrolment fee must be greater than zero"**
An enabled instance needs a price. Use `self` enrolment for free courses.

## The student cannot start a payment

**The pay button is missing**
Check, in order: the instance is enabled; the current date is inside
`enrolstartdate`/`enrolenddate`; the user is not already actively enrolled; the
enrolment cap has not been reached. Managers see the specific reason on the
enrolment page; students see a generic message on purpose.

**"Too many payment attempts in a short time"**
The per-user checkout rate limit (default 10/minute). Usually a double-click
storm. Raise **Checkout rate limit** if your legitimate traffic hits it.

**"The payment could not be created at Mercado Pago"**
The API refused the preference. Turn on **Verbose logging**, retry, and read the
`[ERROR] Mercado Pago API error` line in the server log — it carries the status
code and the first kilobyte of the response body.

Common causes:

| API response | Cause |
| --- | --- |
| `401 invalid_token` | Wrong access token, or a test token while in production mode |
| `400 invalid back_urls` | `$CFG->wwwroot` is `localhost`, an IP, or plain http |
| `400 auto_return invalid` | `auto_return` is on but the success URL is unreachable |
| `400 invalid currency_id` | The currency is not supported by the account's Mercado Pago site |
| `400 invalid_users involved` | Mixing test and production users — the classic sandbox mistake |

## The payment went through but the student is not enrolled

This is the important one. Work down the list.

**1. Look at the transaction report.**

| What you see | What it means |
| --- | --- |
| `approved` / `Active` | The plugin thinks it enrolled them. Check the participants list — the enrolment may exist but be hidden by a group filter |
| `approved` / `Not enrolled` with a `lasterror` | The enrolment was deliberately withheld. Read on |
| `pending` / anything | Mercado Pago has not credited the money yet. Nothing is wrong |
| `Checkout started` | No payment ever arrived for this checkout |

**2. `lasterror` says "does not match the expected amount or currency".**
An approved payment came back for less than the course price, or in another
currency. The plugin never grants access in that case. It usually means the
price was edited between the preference being created and the payment being
made. Verify the payment in the Mercado Pago dashboard, then either honour it
manually (enrol the student with the manual method) or refund it.

**3. `lasterror` says the course reached its maximum.**
The enrolment cap filled up while the buyer was paying. Raise the cap and press
the re-check icon, or refund.

**4. The status is still `Checkout started` and the buyer insists they paid.**
Press the re-check icon on the transaction. If it comes back "Nothing to
reconcile yet", the payment was made against a *different* preference — search
the Mercado Pago dashboard by the buyer's email and compare `external_reference`
values.

**5. Nothing resolves and there are no `webhook_received` events.**
Notifications are not arriving at all. See the next section. The reconciliation
task should still be settling payments; if it is not, check **Site
administration ▸ Server ▸ Tasks ▸ Task logs** for `reconcile_payments`. A site
whose cron is not running will take payments and enrol nobody.

## Notifications are not arriving

**No `webhook_received` events in the site log**

1. Confirm the URL registered in *Your integrations* is exactly
   `https://your-site/enrol/mpcheckoutpro/webhook.php`.
2. Test reachability from outside:
   `curl -i -X POST https://your-site/enrol/mpcheckoutpro/webhook.php -d '{}'`
   A `401` is the correct answer (unsigned) — it proves the request arrives. A
   redirect to a login page, a `403`, or a timeout means something in front of
   Moodle is blocking it.
3. Check for a WAF or `mod_security` rule dropping POSTs with a JSON body, and
   for anything stripping the `x-signature` header.

**`webhook_rejected` events with reason `invalid`**
The webhook secret is wrong. It is *not* the access token — it is the secret
signature beside the webhook configuration in *Your integrations*, and it is
different for the test and production applications. Copy it again, carefully.

**`webhook_rejected` with reason `missing`**
Something removed the `x-signature` header, or the request is not really from
Mercado Pago (probes are common on public URLs). Check the `payload` column of
`enrol_mpcheckoutpro_wh` to see what actually arrived.

**Notifications arrive but time out**
Mercado Pago allows five seconds. If your outbound connection to
`api.mercadopago.com` is slow, the inline API call will not fit. Turn on
**Process notifications in the background**: the endpoint then answers
immediately and the `retry_webhooks` task does the API work.

**Everything looks right but a payment is stuck**
Press the re-check icon in the transaction report. It performs the same
`GET /v1/payments/{id}` the webhook would have triggered and applies the result.
It is safe to press repeatedly — the whole pipeline is idempotent.

## Refunds and chargebacks

**A refunded student still has access**
The action is set to *Keep the enrolment*, or another approved payment from the
same student still covers the course (the plugin deliberately does not revoke
access that a second, valid payment is paying for), or the reversal has not been
reconciled yet. Press the re-check icon.

**A student lost access after a partial refund**
Mercado Pago reports a partial refund as a `refunded` status on the payment. The
plugin treats any `refunded` as a reversal. If you routinely make partial
refunds, set the reversal action to *Keep the enrolment* and handle access
manually.

## Split payments

**"Split payments are not enabled on this site"**
Enable the feature and fill in the application client id and secret at site
level before connecting any seller.

**"The authorisation response could not be matched to a request"**
The CSRF state did not survive the round trip — usually a session that expired
while the seller was authorising, or a different browser. Start the connection
again from the enrolment method settings.

**"The authorisation code could not be exchanged"**
The `redirect_uri` registered in the Mercado Pago application must match
`https://your-site/enrol/mpcheckoutpro/oauth.php` exactly, including the scheme
and any trailing path. The settings page prints the value to register.

**Payments succeed but the commission is wrong**
`marketplace_fee` is an absolute amount in the course currency, not a
percentage. Mercado Pago deducts its own commission first and yours from the
remainder.

## Data and privacy

**A course backup does not carry the payment history**
By design. Transactions are financial records tied to the site, and per-course
credentials are stored outside the `enrol` table precisely so they are never
exported. Use the transaction report's download button for accounting.

**A deleted enrolment method left its transactions behind**
Also by design. `delete_instance()` detaches them (`enrolid` becomes 0) but
keeps them, with `courseid` and `userid` intact, so the money is still
accounted for.

## Getting more detail

Turn on **Verbose logging** temporarily. Every API call then produces a line in
the server error log with the operation, duration and credential source, and
failures carry the status code and response body. Credentials, card data, emails
and identification numbers are redacted. Turn it off again afterwards.

For a single stuck transaction, the `lastapipayload` column of
`enrol_mpcheckoutpro_txn` holds a redacted copy of the last payment resource
the plugin received, which is usually enough to see what Mercado Pago thinks the
state is.
