# Changelog

All notable changes to `enrol_mercadopagocpro` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - v1.1.0

Compliance release for Moodle Marketplace. No functional change.

### Changed

- **Copyright is now held by Julio Tentor & Associates**, with authorship
  attributed separately through `@author`. Applied to all 57 PHP files.
- **`$string['pluginname']` is now "Mercado Pago Checkout Pro (Tentor &
  Associates)".** `enrol_mpcheckoutpro` uses effectively the same words, so a
  site with both installed previously saw two indistinguishable entries in the
  *Add method* dropdown.
- **All files converted to Unix line endings.** The tree was stored with CRLF,
  which the Moodle coding style does not use.
- **`composer.json` removed from the repository.** It recorded how the bundled
  SDK was produced, but its presence invited exactly the Composer run that
  breaks the bundle, and the version pin it documented is already stated in
  `thirdpartylibs.xml`, which is the file Moodle actually reads.
- The Spanish language pack moved out of `lang/` and no longer ships in the
  package. Only English strings ship; translations are contributed through AMOS
  after approval, as the contribution guidelines require.

### Fixed

- **Two settings-page strings described behaviour the plugin does not have.**
  `environment_desc` claimed the buyer is sent to a sandbox checkout in the test
  environment; there is no sandbox checkout — `sandbox_init_point` is a legacy
  field that produces a redirect loop, and the plugin always uses `init_point`.
  `testmodenotice` promised that no real money would be charged, which the
  setting cannot guarantee: whether money moves is decided by the Mercado Pago
  account the credentials belong to, not by which slot they were pasted into.
  Both rewritten, and the credentials section now explains the test seller model
  outright — the account is what makes a payment a test, not the type of
  credential, and a real account's `TEST-` credentials cannot complete a payment
  at all.

- **The plugin contradicted itself about Composer, and the loader was on the
  wrong side of it.** `sdk::register()` preferred a `vendor/autoload.php` inside
  the plugin directory over the bundled SDK, and `README.md`,
  `docs/DEPLOYMENT.md` and `docs/TROUBLESHOOTING.md` all recommended creating one
  — while `cli/diagnose.php` reported those same files as an error state and
  `docs/TESTING.md` warned against producing them. Following the documentation
  therefore replaced the audited bundle that `thirdpartylibs.xml` declares as
  unmodified upstream 3.14.0, silently making that declaration false. The
  bundled copy is now the only source the plugin loads from, a
  `vendor/autoload.php` there is ignored, and the three documents say so. Moodle
  requires that a plugin install without an administrator running Composer, so
  there was never a supported configuration on the other side of this.

- **Incomplete erasure in the privacy provider.** `enrol_mercadopagocpro_wh`
  was neither declared in `get_metadata()` nor deleted by any of the three
  delete methods, so erasing a user left webhook log rows pointing at a `txnid`
  that no longer existed. The table is now declared, exported alongside the
  transactions it belongs to, and deleted with them. Deletion happens before the
  transaction rows go, because once those are gone there is nothing left to find
  the notifications by.
- **`export_user_data()` emitted three fields `get_metadata()` did not
  declare**: `statusdetail`, `enrolmentstate` and `paymenttypeid`. All three are
  now declared, and a new test compares the exported keys against the declared
  ones so the two cannot drift apart again.
- **README claimed PHP 8.2+.** Moodle 5.2 requires PHP 8.3 or later, so the
  stated requirement was wrong in both the badge and the requirements list.
  `composer.json` carried the same wrong constraint.
- README carried a hard-coded `Version: v1.0.0` line that was already stale at
  v1.0.1. Removed rather than corrected: `version.php` is the only place the
  version needs to exist, and this line has drifted once already.

### Added

- **Continuous integration.** `.github/workflows/ci.yml` runs moodle-plugin-ci
  across PHP 8.3/8.4 and PostgreSQL/MariaDB against `MOODLE_502_STABLE`. The
  PostgreSQL leg is the first time this plugin has been exercised on anything
  other than MariaDB.
- `.gitattributes` builds the distribution package with `git archive`, excluding
  development-only files and producing the `mercadopagocpro/` top-level
  directory the installer expects.
- `cli/diagnose.php` now announces the sections it skips. Sections 5, 5b, 9, 10
  and 11 only run when `--courseid`, `--tryadd` or `--fixorphans` is supplied,
  and the numbering used to jump — 4 straight to 6, 8 straight to 11 — which
  reads as something having failed silently. Each skipped section now prints one
  line saying what would run it.
- `docs/ADMINISTRATOR-SETUP.md`, replacing `docs/HUMAN-TASKS.md`: the same
  material stated as what an administrator must configure rather than as a list
  of known gaps.
- The Behat scenario that needs an HTTPS site is tagged
  `@enrol_mercadopagocpro_https`, so it can be run or excluded on its own.
  Continuous integration does not run Behat at all: the site it serves is plain
  http, so that scenario could only ever fail there. Behat remains a pre-release
  check on the HTTPS development site; `docs/TESTING.md` says how to run it.

## [1.0.1] - 2026-08-28

### Fixed

- **README: a typo inverted the meaning of the reliability guarantee.** The
  settlement section read "a lost notification can ever strand a payment"; it
  should be *never*. The sentence describes the whole point of having three
  independent settlement paths, so the wrong word undermined exactly the claim it
  was making. Introduced when the paragraphs were reflowed.
- README: sentences broken mid-line by the same reflow, and grammar in the
  acknowledgements and AI-assistance statement.

### Added

- `docs/TESTING.md` now carries a complete Behat runbook: chromedriver without
  Selenium, the `behat_profiles` block with the Chrome arguments a headless
  server needs, why `behat_wwwroot` must differ from `wwwroot` and how Moodle
  decides a request belongs to the test site, and the mapping between the field
  labels the feature types and the language strings that produce them.

### Changed

- `edit_instance_form()` passes a fallback to `setDefault('status', …)`, matching
  the one `get_instance_defaults()` already used. This is tidiness, not a fix: a
  person adding an instance through the UI always sees "Yes" preselected, because
  `enrol/editinstance.php` sets `$instance->status = ENROL_INSTANCE_ENABLED`
  before the form is built and `set_data()` overrides `setDefault()`. Core does
  this deliberately — the site level setting governs automatically created
  instances — and every enrolment plugin behaves the same way.

### Verified by the first Behat run

Four scenarios, all passing on a real site with chromedriver: adding the method,
the two validation messages, and the student seeing the pay button on an enabled
instance over HTTPS. The run also confirmed that the HTTPS guard on
`edit_instance_validation()` does what it claims — the first attempt, over plain
http, was refused.

### Pending for this release

- Nothing outstanding. The feature file exists and has never been executed;
  it covers the part of the plugin PHPUnit structurally cannot reach — the
  instance form as the browser actually submits it.

## [1.0.0] - 2026-08-25

First release, under the component name `enrol_mercadopagocpro`.
Requires Moodle 5.2.2 (2026042002.00) or a later 5.2 release.

> **Two earlier component names were abandoned before release.**
> `enrol_mp_checkoutpro` could not work at all: the underscore inside the plugin
> name made core's `enrol_plugin::get_name()` resolve it to `mp`, so every instance
> was stored under a plugin that does not exist and vanished from its course.
> `enrol_mpcheckoutpro` worked, but that name is already taken in the Moodle
> plugins directory by an unrelated project.
>
> `mercadopagocpro` is 15 characters, which matters: the core `enrol.enrol` column
> is `char(20)`, so a longer plugin name is rejected outright on a strict database
> and silently truncated on a lax one. `mercadopagocheckoutpro` (22) was ruled out
> for exactly this reason.
>
> There is no upgrade path from either earlier name and none is needed — uninstall
> the old plugin, then install this one. `cli/diagnose.php --fixorphans` clears
> `enrol` rows left behind under `mp` or `mpcheckoutpro`.

### Verified by the first PHPUnit run

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
  `MERCADOPAGOCPRO_ACCESS_TOKEN` would have overridden the fake test token.
- Log context is typed and formatted consistently: `txnid` is always an integer,
  and both sides of an amount mismatch are formatted to two decimals.

### Added

- Checkout Pro enrolment method (`enrol_mercadopagocpro_plugin`) with per course
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

### Fixed relative to the earlier unreleased builds

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
