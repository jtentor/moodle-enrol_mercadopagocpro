# Mercado Pago Checkout Pro - Moodle enrolment plugin

![Moodle Plugin](https://img.shields.io/badge/Moodle-Plugin-orange?style=flat&logo=moodle)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL--3.0-blue.svg?style=flat&logo=gnu&logoColor=white)

`enrol_mercadopagocpro` lets students pay for a course through **Mercado Pago
Checkout Pro** and be enrolled automatically once the payment is credited.

- **Component:** `enrol_mercadopagocpro`
- **Directory:** `enrol/mercadopagocpro`
- **Main class:** `enrol_mercadopagocpro_plugin`
- **Version:** v1.0.0
- **Requires:** Moodle 5.2.2 (`2026042002.00`) or a later release of the 5.2 branch, PHP 8.2+
- **Default currency:** ARS (BRL, CLP, COP, MXN, PEN and UYU are also selectable)
- **Licence:** GPL v3 or later

## How it works

```text
Student clicks "Pay with Mercado Pago"
        │
        ▼
checkout.php ──► transaction row ──► POST /checkout/preferences ──► init_point
        │                                                              │
        │                                                              ▼
        │                                                   Mercado Pago checkout
        │                                                              │
        ├────────────── back_urls ◄────────────────────────────────────┤
        │                    │                                         │
        │                    ▼                                         ▼
        │              return.php                             webhook.php
        │                    │                                         │
        │                    └──────────► GET /v1/payments/{id} ◄──────┘
        │                                        │
        │                                        ▼
        │                            payment_processor decides
        │                                        │
        └──────── reconcile_payments task ───────┘   (safety net, every 10 min)
```

Three independent paths can settle the same payment — the buyer's return, the webhook and the scheduled reconciliation.

All three converge on `GET /v1/payments/{id}` and take a per-transaction lock, so the outcome is the same whichever one arrives first, and a lost notification can ever strand a payment.

## Security model

- **Nothing the browser says is trusted.** The price, the currency and the status all come from the API response, never from a query string or a POST.
- **Every notification is verified.** The `x-signature` header is validated with HMAC-SHA256 through the official SDK validator before anything is processed. Unverifiable notifications are answered with `401` by default.
- **Amount checking.** An approved payment that does not match the expected amount and currency is recorded but never grants access.
- **Credentials never reach the browser.** Checkout Pro is a redirect flow, so no key is published in the page. Per-course credentials are encrypted with `\core\encryption` and stored outside the `enrol` table so they are excluded from course backups.
- **HTTPS is mandatory.** The enrolment method refuses to be enabled on a site that is not served over HTTPS, because Mercado Pago requires it for `notification_url` and `back_urls`.
- **Rate limiting** on both the public webhook endpoint and preference creation.
- **Redaction.** Tokens, card data, emails and identification numbers are stripped from anything written to the database or the log.

## Relationship to other Mercado Pago plugins

Several Mercado Pago enrolment plugins already exist for Moodle, and this one owes them a debt — they mapped the territory first:

- [`enrol_mpcheckoutpro`](https://moodle.org/plugins/enrol_mpcheckoutpro)
  ([redesitos](https://github.com/redesitos/moodle-enrol_mpcheckoutpro)), a
  Checkout Pro integration published for Colombia.
- [`enrol_mercadopagoar`](https://github.com/jpgiecco/moodle-enrol_mercadopagoar), a variant of the above adapted to
  Argentina using Bricks.
- [`enrol_mercadopago`](https://github.com/harregoces/moodle-enrol_mercadopago)
  and [a fork of it](https://github.com/equicomv2/moodle-enrol_mercadopago),
  covering several countries.

`enrol_mercadopagocpro` is a separate component rather than a fork. What it adds is a current codebase written against Moodle 5.2 and the official `mercadopago/dx-php` SDK, documented architecture, and an automated test suite (65 PHPUnit tests) that runs against a real site.

I want to give my recognise to all of them, they push me to develop this plugin first for my needs, second to everyone who need it. Julio Tentor - `jtentor@gmail.com`

The component name is deliberately distinct so that it can be installed alongside any of the above without colliding.

## Earlier names of this component

This plugin was developed under two earlier names, neither of which was ever released: `enrol_mp_checkoutpro` (abandoned because the underscore in the plugin name made core's `enrol_plugin::get_name()` resolve it to `mp`) and `enrol_mpcheckoutpro` (abandoned because that name is already taken in the Moodle plugins directory).

If you are moving from one of those builds, uninstall it first — *Site administration ▸ Plugins ▸ Plugins overview ▸ Uninstall* — and export its transaction report beforehand if you need the payment history, because uninstalling drops its tables. Then remove the old directory from disk and install this plugin. There is no upgrade path and no shared data.

If an earlier build left rows behind in the `enrol` table under the names `mp` or `mpcheckoutpro`, `php enrol/mercadopagocpro/cli/diagnose.php --fixorphans`
removes them.

## AI-Assisted Technology Statement

During the development and documentation of this project, the large languages models Claude Opus 5 (Anthropic, 2026), ChatGPT GPT-5.6 Sol (OpenAI, 2026) and GitHub Copilot (Microsoft, 2026), was utilized.

Specifically, this tools was employed as a technical assistant for the architecture and code generation of a Moodle enrolment plugin, streamlining scriptwriting, system file structuring, and software debugging. To ensure security, compliance with Moodle development standards, and overall software reliability, all code, and logic generated by the platform were thoroughly verified, tested, and critically edited by the author before final implementation. The author maintains full accountability for the functionality, accuracy, and originality of the work presented.

## Installation

1. Copy this directory to `enrol/mercadopagocpro` inside your Moodle installation (`public/enrol/mercadopagocpro` on Moodle 5.x source trees).
2. Visit **Site administration ▸ Notifications** and complete the upgrade.
3. Enable the method in **Site administration ▸ Plugins ▸ Enrolments ▸ Manage enrol plugins**.
4. Configure the credentials in **Site administration ▸ Plugins ▸ Enrolments ▸ Mercado Pago Checkout Pro**.

The official Mercado Pago PHP SDK is bundled under `vendor/mercadopago`, so no Composer step is required. If you prefer to manage it yourself, run `composer install --no-dev` inside the plugin directory; a `vendor/autoload.php` there takes precedence over the bundled copy.

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the full production checklist.

## Configuration

### Site level

| Group | What it covers |
| --- | --- |
| Credentials | Production and test access token, public key and webhook secret; environment switch; whether courses may supply their own credentials |
| Webhooks | Signature enforcement and tolerance, deferred processing, rate limits, reconciliation limits |
| Preference | `auto_return`, `binary_mode`, `purpose` (require a Mercado Pago account), `statement_descriptor`, expiry, installments, excluded payment types and methods, custom metadata |
| Split payments | Marketplace application client id/secret and the identifier sent as `marketplace` |
| Behaviour | Holding enrolments for pending payments, refund/chargeback action, notifications, welcome message default, expiry action, retention |
| Diagnostics | Verbose logging, API timeout and retries, integrator and platform ids |
| Instance defaults | Status, cost, currency, role and duration applied to new instances |

Credentials can also come from `config.php` or the environment, which is the recommended pattern when the database is shared with less trusted environments:

```php
// config.php
$CFG->enrol_mercadopagocpro = [
    'accesstoken'   => getenv('MERCADOPAGOCPRO_ACCESS_TOKEN'),
    'publickey'     => getenv('MERCADOPAGOCPRO_PUBLIC_KEY'),
    'webhooksecret' => getenv('MERCADOPAGOCPRO_WEBHOOK_SECRET'),
];
```

Resolution order is: per-instance credentials → `config.php`/environment → the site settings stored in the database.

### Course level

Each enrolment instance sets its own price, currency, role, duration, start and end dates, group, and enrolment cap, plus per-course overrides for installments,
excluded payment types and methods, item description, metadata, holding enrolments, notifications, the refund action and, when split payments are on, the marketplace commission and seller.

It also carries a **course welcome message**, behaving exactly as in `enrol_self`: pick who it comes from, optionally write your own text with the standard placeholders (`{$a->firstname}`, `{$a->coursename}`, `{$a->courselink}`, …), and it is sent once, when the payment is approved and the enrolment becomes active — never for a payment that is still pending. Core's "from the key holder" option is not offered, because a paid enrolment has no key holder.

## Endpoints

| File | Reachable by | Purpose |
| --- | --- | --- |
| `checkout.php` | logged-in user, sesskey | Creates the preference and redirects to Mercado Pago |
| `return.php` | logged-in user | The three `back_urls`; re-queries the API and shows the result |
| `webhook.php` | Mercado Pago, no session | Receives, verifies and dispatches notifications |
| `oauth.php` | manager, sesskey | Connects a marketplace seller through OAuth |
| `transactions.php` | `enrol/mercadopagocpro:viewtransactions` | Per-course payment report |
| `cli/diagnose.php` | CLI | Checks the whole install and says why the method may not appear |

## Documentation

- [`docs/API.md`](docs/API.md) — every Mercado Pago endpoint and field the plugin uses, with the source in the official documentation.
- [`docs/TESTING.md`](docs/TESTING.md) — unit tests, Behat and end-to-end testing with Mercado Pago test users.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — production checklist and hardening.
- [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) — symptoms, causes and fixes.
- [`docs/HUMAN-TASKS.md`](docs/HUMAN-TASKS.md) — the prioritised list of things only a human can do: credentials, webhook registration, business decisions, and the known gaps.
- [`CHANGELOG.md`](CHANGELOG.md) — release history.

## References

- [Checkout Pro overview](https://www.mercadopago.com.ar/developers/en/reference/online-payments/checkout-pro/overview)
- [Create preference](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/preferences/create-preference/post)
- [Configure return URLs](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/configure-back-urls)
- [Webhooks](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks)
- [Mercado Pago PHP SDK](https://github.com/mercadopago/sdk-php)
- [Moodle enrolment plugin API](https://moodledev.io/docs/5.2/apis/plugintypes/enrol)
- [Anthropic. Claude Opus 5](https://claude.ai)
- [OpenAI. ChatGPT GPT-5.6 Sol](https://chat.openai.com)
- [Microsoft. GitHub Copilot](https://github.com/features/copilot)
