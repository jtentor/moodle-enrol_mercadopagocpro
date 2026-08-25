# Changelog

All notable changes to `enrol_mpcheckoutpro` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

First execution of the PHPUnit suite (65 tests, 191 assertions, all passing on
Moodle 5.2.2 / PHP 8.4.14 / MariaDB 12.0.2). It found three defects, fixed here:

### Fixed

- `util::encode_for_storage()` reserved 16 characters for a 17 character
  truncation marker, so a capped payload was always one byte over the requested
  maximum. The room is now derived from the marker itself.
- `api_client::call()` cast `MPResponse::getContent()` to string. The SDK declares
  that method `: array`, so every Mercado Pago API error logged the literal
  `"Array"` and raised a PHP warning, discarding the diagnostic Mercado Pago sends
  back. The decoded body is now passed through `redact()` and stored intact.
- The test harness loaded `tests/fixtures/mock_http_client.php` before the
  Mercado Pago autoloader was registered. Since the fixture declares
  `implements MPHttpClient`, PHP could not compile it and six of the seven test
  files aborted before running.

### Changed

- The test suite no longer inherits server level credentials. `credentials::resolve()`
  ranks the server configuration above the site settings, and PHPUnit's bootstrap
  discards `$CFG` but not the process environment, so a production
  `MPCHECKOUTPRO_ACCESS_TOKEN` would have overridden the fake test token.
- Log context is typed and formatted consistently: `txnid` is always an integer,
  and both sides of an amount mismatch are formatted to two decimals.

## [1.0.0] - 2026-08-23

First release. Requires Moodle 5.2.2 (2026042002.00) or a later 5.2 release.

> **Replaces the unreleased `enrol_mp_checkoutpro`.** That component name put an
> underscore inside the plugin name, which core cannot carry: `enrol_plugin::get_name()`
> derives the name from the second word of the class, so every instance was stored
> under a plugin called `mp` and disappeared from its course. There is no upgrade
> path and none is needed — uninstall the old plugin, then install this one.

### Added

- Checkout Pro enrolment method (`enrol_mpcheckoutpro_plugin`) with per course
  price, currency, role, duration, start/end dates, group assignment and a cap on
  the number of enrolled users.
- **Course welcome message**, following `enrol_self`: a send option and a custom
  message with the standard placeholders, stored in the same `customint4` /
  `customtext1` columns and delivered through core's
  `enrol_plugin::send_course_welcome_message_to_user()`. Sent once, when a payment
  is approved and the enrolment becomes active. Core's "from the key holder"
  option is omitted because a paid enrolment has no key holder.
- Payment preference creation through the official Mercado Pago PHP SDK, with an
  idempotency key per local transaction so a retried request cannot produce a
  duplicate preference.
- Advanced preference options: excluded payment types and methods, maximum and
  preselected installments, preselected payment method, binary mode, statement
  descriptor, preference expiry, item description and category, and custom
  metadata fields on top of the Moodle ids the plugin always sends.
- Optional `purpose=wallet_purchase`, restricting the checkout to buyers logged
  in to a Mercado Pago account so account money and saved cards are offered.
- Split payments (marketplace): OAuth seller connection per enrolment instance
  and `marketplace_fee` on the preference.
- Webhook endpoint with `x-signature` HMAC-SHA256 validation, rate limiting,
  de-duplication, an audit log and internal retries.
- Return handler for the three `back_urls` that always re-queries
  `GET /v1/payments/{id}` before deciding anything.
- Scheduled reconciliation of every non-final transaction, so a lost notification
  can never strand a payment.
- Enrolment state machine: enrol on approved, optional holding enrolment for
  pending offline payments, and configurable suspend/unenrol on refund,
  chargeback or cancellation.
- Notifications to buyers and to course staff, plus standard enrolment expiry
  notifications.
- Course level transaction report with per status totals, CSV/Excel download and
  a manual re-check action.
- Site level and per enrolment instance credentials, the latter encrypted with
  `\core\encryption` and excluded from course backups.
- Privacy API provider covering the stored transactions and the data sent to
  Mercado Pago.
- English and Spanish language packs.
- `cli/diagnose.php`, which checks installation, class loading, enabled state,
  `get_name()`, capabilities, the "Add method" dropdown, HTTPS, the SDK, the
  database tables, the credentials, the scheduled tasks, and simulates both
  building and saving the instance form.
- PHPUnit test suite with a mock HTTP client, and a Behat feature.

### Fixed relative to the unreleased `enrol_mp_checkoutpro`

- The plugin name no longer contains an underscore, so `get_name()` resolves
  correctly without an override and instances stay attached to their course.
- `edit_instance_validation()` checked `!empty($data['status'])` before verifying
  credentials and HTTPS. `ENROL_INSTANCE_ENABLED` is `0`, so that guard could
  never run for an enabled instance. It now uses `isset()`.
- The transaction report built its user name SQL with `get_sql(..., leadingcomma:
  false)` while concatenating the result, producing invalid SQL.
- `webhook.php` defined `ABORT_AFTER_CONFIG` as `false`. Moodle tests it with
  `defined()`, so the endpoint aborted during setup.
- The buyer was redirected to `sandbox_init_point` when the environment was set
  to Test. That legacy URL redirect-loops (`ERR_TOO_MANY_REDIRECTS`). The plugin
  now always uses `init_point`; test mode is a matter of credentials and test
  users, per the official testing guide.
