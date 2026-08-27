# Testing

## Automated tests

### PHPUnit

From your Moodle root, after initialising the test environment:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite enrol_mercadopagocpro_testsuite
```

or a single file:

```bash
vendor/bin/phpunit enrol/mercadopagocpro/tests/payment_processor_test.php
```

No test reaches the network. `tests/fixtures/mock_http_client.php` implements the
SDK's `MPHttpClient` interface and answers from a queue of canned responses;
`tests/helper_trait.php` installs it with
`MercadoPagoConfig::setHttpClient()` and sets up credentials, HTTPS and an
enabled plugin.

| File | What it covers |
| --- | --- |
| `util_test.php` | Signed external references (round trip, tampering, foreign values), redaction, payload capping |
| `preference_builder_test.php` | The whole preference body: items, payer, back_urls, `auto_return`, metadata, payment method rules, installments capping, expiry format, descriptor sanitising, `marketplace_fee` |
| `checkout_service_test.php` | Transaction creation, idempotency header, preference reuse, sandbox init point, every refusal path, API failure recording |
| `payment_processor_test.php` | Approved → enrolled, idempotency, rejected, pending holding and activation, refund → suspend, chargeback → unenrol, underpayment, wrong currency, reference mismatch, group assignment, enrolment cap |
| `webhook_handler_test.php` | Notification normalisation (including the legacy `topic` form), valid/invalid/missing signature, audit logging, unknown resources, unhandled types, oversized bodies, rate limiting |
| `plugin_test.php` | Instance CRUD, advanced options round trip, form validation, credential encryption and fallback, deletion behaviour, setting fallbacks, expiry sync, and the course welcome message (settings round trip, sent on approval, disabled, not sent while pending) |
| `privacy_provider_test.php` | Metadata, context and user discovery, export, and all three deletion paths |

### Behat

The feature covers adding the method to a course, the validation messages and the
student-facing enrolment page. It deliberately stops at the redirect: the Mercado
Pago checkout itself is out of Behat's reach.

Three of the four scenarios are `@javascript`, so a real browser is required.
These instructions use **chromedriver on its own**, with no Selenium and no Java.

Run all of this on a throwaway clone, never on a production site.

#### 1. Browser and driver

```bash
sudo apt install -y chromium chromium-driver
chromium --version && chromedriver --version    # the major versions must match
```

#### 2. Configuration

In `config.php`, before `require_once(__DIR__ . '/public/lib/setup.php');`:

**The Behat site must be served over HTTPS.** The plugin refuses to enable an
enrolment instance on a site that is not, because Mercado Pago rejects plain http
`notification_url` and `back_urls`. That check reads `$CFG->wwwroot`, which during
a Behat run *is* `$CFG->behat_wwwroot` (`lib/setup.php` assigns it). A plain-http
Behat site therefore cannot exercise any scenario that enables an instance.

Serve the same `public/` directory from a second Apache listener with the
self-signed certificate the machine already has:

```apache
Listen 8443
<VirtualHost _default_:8443>
    DocumentRoot "/path/to/moodle/public"
    SSLEngine on
    SSLCertificateFile    "/opt/bitnami/apache/conf/bitnami/certs/server.crt"
    SSLCertificateKeyFile "/opt/bitnami/apache/conf/bitnami/certs/server.key"
    <Directory "/path/to/moodle/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Then in `config.php`, before `require_once(__DIR__ . '/public/lib/setup.php');`:

```php
$CFG->behat_wwwroot  = 'https://127.0.0.1:8443';
$CFG->behat_prefix   = 'beh_';
$CFG->behat_dataroot = '/var/moodledata_behat';
$CFG->behat_faildump_path = '/var/behat_faildumps';

$CFG->behat_profiles = [
    'chrome' => [
        'browser'  => 'chrome',
        'wd_host'  => 'http://127.0.0.1:4444/wd/hub',
        'capabilities' => [
            'extra_capabilities' => [
                'goog:chromeOptions' => [
                    'args' => [
                        'headless=new',
                        'no-sandbox',
                        'disable-dev-shm-usage',
                        'disable-gpu',
                        'window-size=1366,768',
                        'ignore-certificate-errors',
                        'allow-insecure-localhost',
                    ],
                ],
            ],
        ],
    ],
];
```

Notes on the values, all of which matter:

- **`behat_wwwroot` must differ from `$CFG->wwwroot`** — Moodle refuses to start
  otherwise (`lib/behat/lib.php`). It decides a request belongs to the Behat site
  by matching **host, port and path** against this value, which is why a second
  port on the same machine is enough. Changing it later requires
  `php public/admin/tool/behat/cli/util.php --drop` and a fresh `init.php`.
- **`behat_dataroot` must be a new, empty directory**, distinct from both
  `dataroot` and `phpunit_dataroot`.
- **Write the Chrome arguments without the leading `--`.** Moodle strips it
  anyway (`behat_config_util::get_behat_profile()`), so both forms work, but this
  is the form the code stores.
- `headless=new` and `no-sandbox` are what make Chrome usable on a server with no
  display and no desktop user. `disable-dev-shm-usage` avoids crashes on the small
  `/dev/shm` that container-derived images tend to have.
- `behat_faildump_path` is worth setting for a first run: Moodle drops a
  screenshot and an HTML dump there for every failing scenario.

#### 3. Initialise

```bash
cd /path/to/moodle
php public/admin/tool/behat/cli/init.php
```

This takes several minutes: it installs a complete second Moodle site into
`beh_*` and generates the Behat configuration. **Read its final lines** — it
prints the exact path of the generated `behat.yml`, which is what the direct
`vendor/bin/behat` invocation needs.

Re-run `init.php` after installing or upgrading any plugin, exactly as with
PHPUnit.

#### 4. Start the two background processes

In one terminal:

```bash
chromedriver --port=4444 --url-base=wd/hub
```

Port 4444 with `--url-base=wd/hub` is chosen so the address matches what Moodle
uses by default; chromedriver's own default is port 9515 at the root path.

The Apache listener from step 2 serves the site itself, so chromedriver is the
only process to start by hand.

PHP's built-in server (`php -S`) also works for a plain-http Behat site, but it
cannot terminate TLS, and it is single threaded unless `PHP_CLI_SERVER_WORKERS`
is set — Moodle issues concurrent requests during a scenario, so without workers
a run can simply hang.

#### 5. Run

```bash
cd /path/to/moodle
php public/admin/tool/behat/cli/run.php --profile=chrome --tags=@enrol_mercadopagocpro
```

To run a single scenario while debugging, use the generated configuration
directly with the line number of the scenario:

```bash
vendor/bin/behat --config <behatdataroot>/behatrun/behat/behat.yml \
  --profile=chrome \
  public/enrol/mercadopagocpro/tests/behat/enrol_mercadopagocpro.feature:29
```

#### Field labels

The labels the feature types into the instance form are resolved from language
strings, and a mismatch is the usual cause of a first-run failure. The current
mapping is:

| Label in the feature | Source |
| --- | --- |
| `Enrolment fee` | `cost`, this plugin |
| `Currency` | `currency`, this plugin |
| `Allow Mercado Pago enrolments` | `status`, this plugin |
| `Custom instance name` | `custominstancename`, core |

If any of those strings change, the feature has to change with them.

### Code checks

Install the tooling **in the Moodle root**, never inside the plugin:

```bash
cd /path/to/moodle          # the Moodle root, NOT enrol/mercadopagocpro
composer require --dev moodlehq/moodle-cs
```

Running composer inside `enrol/mercadopagocpro` looks equivalent and is not: the
plugin has its own `composer.json`, so composer would resolve it, install a second
copy of the Mercado Pago SDK under `vendor/mercadopago/dx-php`, and create
`vendor/autoload.php`. `sdk::register()` prefers that autoloader over the bundled
sources, so the audited SDK would be silently shadowed — and the development tools
would end up inside the plugin, ready to ship with it. `cli/diagnose.php` checks
for both situations.

Then run `phpcs` and `phpcbf` from **inside the plugin directory** so they pick up
the bundled `.phpcs.xml`, which is the only configuration that is safe here:

```bash
cd public/enrol/mercadopagocpro       # drop "public/" on a pre-5.0 layout
../../../vendor/bin/phpcs             # uses .phpcs.xml
../../../vendor/bin/phpcbf            # same rules, applies the fixes
cd ../../..
php public/admin/cli/check_database_schema.php
```

The binary comes from the Moodle root `vendor/`; the ruleset comes from the
current directory. That is why the `cd` matters.

`.phpcs.xml` does two things that matter, and running `phpcbf` without it will
damage the plugin:

- **It excludes `vendor/`.** The Mercado Pago SDK is third party code and
  `thirdpartylibs.xml` declares it unmodified. `phpcbf` reformats 246 of its 250
  files — cosmetic, but it destroys the byte identity that makes the bundle
  auditable and upgradeable.
- **It disables `PEAR.Files.IncludingFile`.** That sniff rewrites a conditional
  `require_once` into `include_once`, which is not equivalent: `include_once`
  only warns when the file is missing, so a broken library path surfaces much
  later as an undefined function instead of failing at the include. Moodle core
  uses `require_once` for libraries everywhere, conditionals included.

If you prefer to pass the standard explicitly, the equivalent is:

```bash
vendor/bin/phpcs --standard=moodle-extra \
  --exclude=PEAR.Files.IncludingFile \
  --ignore=enrol/mercadopagocpro/vendor \
  enrol/mercadopagocpro
```

## Manual testing with Mercado Pago test credentials

Automated tests prove the plugin's logic. They cannot prove your Mercado Pago
account is configured correctly — only a real end-to-end run does that.

### 1. Set up the test environment

1. In the Mercado Pago developer dashboard, create (or open) your application
   and copy the **test** credentials.
2. In Moodle, set **Environment** to *Test*, fill in the test access token,
   public key and webhook secret, and save.
3. Create two [test users](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/test/accounts):
   one seller (whose credentials you just used) and one buyer.
4. Configure the webhook URL in *Your integrations*:
   `https://your-site/enrol/mercadopagocpro/webhook.php`

Your site must be reachable from the internet over HTTPS for notifications to
arrive. For local work, tunnel it (`ngrok`, `cloudflared`) and set `$CFG->wwwroot`
to the tunnel URL — Mercado Pago rejects `localhost` and `127.0.0.1`.

**Always test in an incognito window** (or a separate browser profile), logged in
as the test buyer. Mixing your real Mercado Pago session with a test user's is
what produces cookie errors and redirect loops. The plugin sends the buyer to
`init_point` in every environment; `sandbox_init_point` is a legacy field that
redirect-loops and is never used.

### 1b. The buyer's Mercado Pago session decides what they can pay with

This catches everyone out, so get it straight before you start:

- **Log the test buyer into Mercado Pago first**, in the same incognito window,
  *before* going to the Moodle course. Only then does the checkout offer **money
  in the Mercado Pago account and saved cards**. Without a session it falls back
  to guest checkout, which is card-entry only.
- **Do not expect to log in from inside the payment flow.** Once Moodle has
  redirected to the checkout, signing in there is unreliable, and in a fresh
  incognito window it generally does not work at all. Establish the session
  first, then start the purchase.

So the working order in an incognito window is:

1. Open mercadopago.com.ar and sign in as the **test buyer**.
2. In the same window, open the Moodle site and log in as the student.
3. Go to the course and press **Pay with Mercado Pago**.

If you need buyers to always have account money and saved cards available, turn
on **Require a Mercado Pago account** in the plugin settings. That sends
`purpose=wallet_purchase` and restricts the checkout to logged-in accounts —
but then guests cannot pay at all, and cash coupons and bank transfer disappear.

### 2. Test cards

Log in as the **buyer** test user before paying.

| Card | Number | CVV | Expiry |
| --- | --- | --- | --- |
| Mastercard credit | 5031 7557 3453 0604 | 123 | 11/30 |
| Visa credit | 4509 9535 6623 3704 | 123 | 11/30 |
| Amex credit | 3711 803032 57522 | 1234 | 11/30 |
| Mastercard debit | 5287 3383 1025 3304 | 123 | 11/30 |
| Visa debit | 4002 7686 9439 5619 | 123 | 11/30 |

Force an outcome with the **cardholder name**:

| Name | Outcome |
| --- | --- |
| `APRO` | Approved |
| `CONT` | Pending |
| `OTHE` | Rejected, general error |
| `CALL` | Rejected, validation required |
| `FUND` | Rejected, insufficient funds |
| `SECU` | Rejected, invalid security code |
| `EXPI` | Rejected, expiry problem |
| `FORM` | Rejected, form error |

Source: [Test cards](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/test/cards).

### 3. Scenarios to run

Tick these off before going live. Each one should end with the transaction
report and the participants list agreeing with each other.

**Happy path**

- [ ] Pay with `APRO`. The buyer lands on the success page, is enrolled, has the
      configured role, and is in the configured group.
- [ ] The buyer receives the "Payment approved" notification; a teacher receives
      the staff notification.
- [ ] The transaction report shows `approved` / `Active` with the payment id.

**Pending**

- [ ] Pay with `CONT`, or with a cash coupon. The buyer sees the pending page.
- [ ] With holding enrolments on, the buyer appears as suspended and cannot
      enter the course.
- [ ] Approve the payment from the seller test account (or wait). Within one
      reconciliation cycle the enrolment becomes active without anyone touching
      the browser.

**Rejected**

- [ ] Pay with `FUND`. No enrolment is created and the buyer can try again.
- [ ] The second attempt reuses the same preference while it is still valid.

**Reversal**

- [ ] Refund an approved payment from the Mercado Pago dashboard.
- [ ] The enrolment is suspended (or removed, per the configured action) within a
      reconciliation cycle, and the buyer is notified.
- [ ] Repeat with the action set to *Keep the enrolment* and confirm nothing changes.

**Resilience**

- [ ] Block outbound traffic to `api.mercadopago.com`, start a checkout, confirm
      the buyer gets a friendly error and the transaction records `lasterror`.
- [ ] Turn the webhook URL off in the dashboard, pay, and confirm the scheduled
      reconciliation still enrols the buyer.
- [ ] Send a hand-crafted POST to `webhook.php` with a wrong `x-signature` and
      confirm a `401` and a `webhook_rejected` event.
- [ ] Replay a valid notification several times and confirm exactly one enrolment.
- [ ] Open the checkout in two tabs at once and confirm one preference and one
      enrolment.

**Welcome message**

- [ ] Set it to *From the course contact*, write a custom text using
      `{$a->firstname}` and `{$a->coursename}`, pay with `APRO`, and confirm the
      buyer receives it with the placeholders filled in and the right sender.
- [ ] Set it to *From the no-reply address* and confirm the sender changes.
- [ ] With a pending payment (`CONT`), confirm **no** welcome message is sent
      until it is approved.
- [ ] Leave the custom text empty and confirm the core default wording is used.

**Configuration**

- [ ] Exclude `ticket` and confirm cash coupons disappear from the checkout.
- [ ] Set installments to 3 and confirm the checkout offers at most three.
- [ ] Set an enrolment cap of 1, enrol one buyer, and confirm the second is refused.
- [ ] Set a preference validity of five minutes, wait, and confirm the link expires.

**Split payments** (only if you use them)

- [ ] Connect a seller through `oauth.php` and confirm the seller id appears.
- [ ] Pay and confirm the money lands in the seller's account and the commission
      in the marketplace account.
- [ ] Confirm a commission greater than or equal to the price is refused by the form.

### 4. Before switching to production

- [ ] Switch **Environment** to *Production* and enter the production credentials
      **and the production webhook secret** — it is different from the test one.
- [ ] Re-run at least one small real payment end to end, then refund it.
- [ ] Confirm the production webhook URL is registered in *Your integrations*.
