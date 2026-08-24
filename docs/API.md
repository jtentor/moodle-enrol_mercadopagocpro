# API reference

Every Mercado Pago endpoint, field and header this plugin touches, and where it
comes from in the official documentation. Nothing outside this list is sent or
read. If you extend the plugin, keep this document in step with the code.

## Endpoints used

| Method | Path | Used by | Documentation |
| --- | --- | --- | --- |
| `POST` | `/checkout/preferences` | `checkout_service` | [Create preference](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/preferences/create-preference/post) |
| `GET` | `/checkout/preferences/{id}` | `api_client::get_preference()` (diagnostics only) | [Get preference](https://www.mercadopago.com.ar/developers/en/reference/online-payments/checkout-pro/preferences/get-preference/get) |
| `GET` | `/v1/payments/{id}` | `payment_processor`, the return page, the reconciliation task | [Checkout Pro overview](https://www.mercadopago.com.ar/developers/en/reference/online-payments/checkout-pro/overview) |
| `GET` | `/merchant_orders/{id}` | `payment_processor::process_merchant_order()` | [Get merchant order](https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/merchant_orders/get-merchant-order/get) |
| `POST` | `/oauth/token` | `oauth_helper` (split payments only) | [OAuth](https://www.mercadopago.com.br/developers/en/docs/security/oauth/creation) |

All calls go through the official SDK clients (`PreferenceClient`,
`PaymentClient`, `MerchantOrderClient`, `OAuthClient`) with a per-call
`RequestOptions` carrying the access token, so a course using a connected seller
never inherits the site credentials.

## The preference body

Built by `enrol_mpcheckoutpro\local\preference_builder`. Fields marked
*conditional* are omitted entirely when they are not configured.

| Field | Value | Notes |
| --- | --- | --- |
| `items[0].id` | `enrol_mpcheckoutpro-{enrolid}` | |
| `items[0].title` | Course full name | Truncated to 256 characters |
| `items[0].description` | Instance description, or "Enrolment in {course}" | Truncated to 600 characters |
| `items[0].category_id` | `learnings` by default | Per-instance override |
| `items[0].quantity` | `1` | Always one enrolment |
| `items[0].currency_id` | Instance currency | ARS by default |
| `items[0].unit_price` | Instance cost, rounded to 2 decimals | |
| `payer.email` | Moodle user email | |
| `payer.name` / `payer.surname` | Moodle first/last name | Omitted when empty |
| `back_urls.success` / `.pending` / `.failure` | `return.php?txn={id}&result=…` | Always https |
| `auto_return` | `approved` *(conditional)* | The only documented value |
| `notification_url` | `webhook.php?enrolid={id}` | The `enrolid` parameter is ours; Mercado Pago preserves it and appends its own |
| `external_reference` | `mpcp-{enrolid}-{userid}-{txnid}-{hmac16}` | HMAC-signed so it can be trusted before any database lookup |
| `binary_mode` | Site setting, boolean | |
| `statement_descriptor` | *(conditional)* | Sanitised to `[A-Za-z0-9 ]`, 22 characters |
| `expires`, `expiration_date_from`, `expiration_date_to` | *(conditional)* | ISO 8601 with milliseconds and offset |
| `payment_methods.installments` | *(conditional)* | Maximum installments |
| `payment_methods.default_installments` | *(conditional)* | Never above `installments` |
| `payment_methods.default_payment_method_id` | *(conditional)* | |
| `payment_methods.excluded_payment_types` | *(conditional)* | `[{"id": "ticket"}, …]` |
| `payment_methods.excluded_payment_methods` | *(conditional)* | `[{"id": "amex"}, …]` |
| `metadata` | Moodle ids plus configured custom fields | See below |
| `marketplace_fee` | *(conditional)* | Split payments only |
| `marketplace` | *(conditional)* | Split payments only |

### Metadata

Always sent:

```json
{
  "moodle_site": "moodle.example.edu",
  "moodle_component": "enrol_mpcheckoutpro",
  "moodle_txn_id": 1234,
  "moodle_enrol_id": 56,
  "moodle_course_id": 78,
  "moodle_course_shortname": "CS101",
  "moodle_user_id": 910,
  "enrolment_period": 31536000,
  "plugin_release": "v1.0.0"
}
```

Site and course level custom metadata is merged on top, with keys lower-cased
and non-alphanumeric characters replaced by underscores. Existing keys are never
overwritten. **Never put personal data in custom metadata** — it leaves the site.

### Response

`init_point` is used in production, `sandbox_init_point` in the test
environment. `id` is stored as `preferenceid` on the transaction.

## Payment statuses

The nine statuses documented for the Payments API, and what the plugin does with
each:

| Status | Enrolment action |
| --- | --- |
| `approved` | Enrol (or activate a holding enrolment) |
| `authorized` | No access — an authorisation hold is not a credited payment. Keeps polling |
| `in_process` | Holding enrolment if enabled. Keeps polling |
| `pending` | Holding enrolment if enabled. Keeps polling |
| `in_mediation` | No change. Keeps polling, because a mediation can end either way |
| `rejected` | No access. Terminal |
| `cancelled` | Revoke if access was granted. Terminal |
| `refunded` | Revoke. Terminal |
| `charged_back` | Revoke. Terminal |

"Revoke" means keep, suspend or unenrol, depending on the configured action, and
only when no other approved payment of the same user still covers the course.

Source: [Get payment status](https://www.mercadopago.com.ar/developers/en/docs/checkout-api-payments/response-handling/query-results).

## Return URL parameters

Mercado Pago appends `payment_id`, `status`, `collection_id`,
`collection_status`, `external_reference`, `payment_type`, `merchant_order_id`,
`preference_id`, `site_id`, `processing_mode` and `merchant_account_id` to the
`back_url`.

The plugin uses only `payment_id` / `collection_id` / `merchant_order_id` to
decide *which resource to look up*, and verifies `external_reference` against
the transaction. Everything else is ignored: the displayed outcome always comes
from a fresh `GET /v1/payments/{id}`.

Source: [Configure return URLs](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/configure-back-urls).

## Webhook notifications

### Request

```
POST /enrol/mpcheckoutpro/webhook.php?enrolid=56&type=payment&data.id=1122334455
x-signature: ts=1755780000,v1=<hex>
x-request-id: <uuid>

{
  "id": 12345,
  "live_mode": true,
  "type": "payment",
  "date_created": "2026-08-21T10:04:58.396-04:00",
  "user_id": 44444,
  "api_version": "v1",
  "action": "payment.updated",
  "data": { "id": "1122334455" }
}
```

The `enrolid` query parameter is added by the plugin to its `notification_url`
so the endpoint can pick the right webhook secret before making any API call.
When notifications are configured in the Mercado Pago dashboard instead of per
preference, the parameter is absent and the site credentials are used.

### Signature validation

Manifest template, exactly as documented:

```
id:[data.id];request-id:[x-request-id];ts:[ts];
```

Pairs whose value is missing are omitted. The HMAC-SHA256 of that manifest,
keyed with the secret signature from *Your integrations*, must equal the `v1`
value in `x-signature`. The comparison uses `hash_equals`.

Validation is performed by `MercadoPago\Webhook\WebhookSignatureValidator` from
the official SDK.

### Responses

| Status | Meaning |
| --- | --- |
| `200` | Accepted. Processed, deferred, duplicate, or not ours |
| `401` | Signature missing or invalid while enforcement is on |
| `405` | Not a POST |
| `413` | Body larger than 64 KB |
| `429` | Rate limit exceeded |
| `500` | Unhandled error — Mercado Pago will retry |
| `503` | The enrolment method is disabled site-wide |

Mercado Pago expects a `200`/`201` within five seconds and retries at 15 min,
30 min, 6 h, 48 h and 96 h. Because that window is tight, the plugin never
returns an error for a transient API failure: it answers `200`, schedules its
own retry, and lets the reconciliation task catch anything that slips through.
Sites with slow outbound connectivity should turn on **Process notifications in
the background**.

### Handled types

`payment` and `merchant_order`. Every other type is acknowledged with `200` and
ignored, so Mercado Pago does not keep retrying notifications from other
integrations that share the account.

Source: [Webhooks](https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks).

## Split payments

In the split payments (marketplace) model the preference is created with the
**seller's** access token, obtained through OAuth, and the marketplace
commission travels in `marketplace_fee`. Mercado Pago deducts its own commission
first and the marketplace commission from the remainder.

The plugin drives the documented flow:

1. `oauth.php?action=connect` builds the authorization URL
   (`https://auth.mercadopago.com/authorization`) with `client_id`,
   `response_type=code`, `platform_id`, `redirect_uri` and a CSRF `state`.
2. The seller authorises and is redirected back to `oauth.php`.
3. The code is exchanged at `POST /oauth/token`, and `access_token`,
   `refresh_token`, `public_key` and `user_id` are stored encrypted against the
   enrolment instance.

Sources: [Integrate checkout in marketplace](https://www.mercadopago.com.br/developers/en/docs/checkout-pro/how-tos/integrate-marketplace),
[OAuth](https://www.mercadopago.com.br/developers/en/docs/security/oauth/creation).

## Enrol table column mapping

| Column | Holds |
| --- | --- |
| `customint1` | Group to add the buyer to, 0 = none |
| `customint2` | Maximum installments, 0 = site setting |
| `customint3` | Holding enrolment for pending payments, -1 = site setting |
| `customint4` | Course welcome message send option (`ENROL_SEND_EMAIL_FROM_*`) |
| `customint5` | Maximum enrolled users, 0 = unlimited |
| `customint6` | Action on refund / chargeback, -1 = site setting |
| `customint7` | Preselected installments |
| `customint8` | Split payments enabled for this instance |
| `customchar1` | Short description shown on the enrolment page |
| `customchar2` | `default_payment_method_id` |
| `customchar3` | Mercado Pago seller (collector) id |
| `customdec1` | `marketplace_fee` |
| `customtext1` | Custom course welcome message |
| `customtext2` | JSON: excluded payment types and methods, item description, category, extra metadata, notification toggle |

`customint4` and `customtext1` deliberately match the columns `enrol_self` uses
for the same two settings, so the welcome message behaves identically and the
core helpers can be used unchanged.

## Internal PHP API

| Class | Responsibility |
| --- | --- |
| `local\checkout_service` | Validates, creates the transaction, calls the API, returns the redirect URL |
| `local\preference_builder` | Builds the preference body |
| `local\api_client` | SDK wrapper, per-call token, error translation |
| `local\payment_processor` | Payment status → enrolment decision |
| `local\enrolment_manager` | Enrol, hold, revoke, groups, locking |
| `local\webhook_handler` | Receive, verify, dispatch, log, retry |
| `local\transaction` | Persistence for `enrol_mpcheckoutpro_txn` |
| `local\credentials` | Credential resolution, encryption at rest |
| `local\instance_settings` | Site settings merged with per-instance overrides |
| `local\status` | The status vocabulary and the state groups |
| `local\oauth_helper` | Split payments seller connection |
| `local\rate_limiter` | Fixed-window limiting on the public endpoints |
| `local\util` | Signed references, redaction, logging |

## Events

| Event | Raised when |
| --- | --- |
| `\enrol_mpcheckoutpro\event\preference_created` | A checkout is started |
| `\enrol_mpcheckoutpro\event\payment_approved` | A payment is approved and the enrolment is activated |
| `\enrol_mpcheckoutpro\event\payment_reversed` | A refund, chargeback or cancellation changes the enrolment |
| `\enrol_mpcheckoutpro\event\payment_updated` | Any other status change |
| `\enrol_mpcheckoutpro\event\webhook_received` | A notification is accepted |
| `\enrol_mpcheckoutpro\event\webhook_rejected` | A notification fails signature validation |

## Database

`enrol_mpcheckoutpro_txn` — one row per checkout, carrying the preference, the
payment, the money, the resulting enrolment state and a redacted copy of the
last API payload. `courseid` and `userid` are denormalised so the row survives
the deletion of the enrolment instance.

`enrol_mpcheckoutpro_wh` — the notification audit log, including rejected ones,
with retry bookkeeping.

`enrol_mpcheckoutpro_cred` — per-instance credentials, encrypted, deliberately
outside the `enrol` table so they are never included in a course backup.
