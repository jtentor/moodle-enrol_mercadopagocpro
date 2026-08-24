# Changelog

All notable changes to `enrol_mpcheckoutpro` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
