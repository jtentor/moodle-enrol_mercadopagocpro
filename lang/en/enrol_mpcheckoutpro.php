<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for the Mercado Pago Checkout Pro enrolment plugin.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mercado Pago Checkout Pro';
$string['pluginname_desc'] = 'The Mercado Pago Checkout Pro enrolment method lets students pay for a course through Mercado Pago and be enrolled automatically once the payment is credited.';

// ---------------------------------------------------------------- Capabilities.
$string['mpcheckoutpro:config'] = 'Configure Mercado Pago Checkout Pro enrolment instances';
$string['mpcheckoutpro:manage'] = 'Manage enrolled users';
$string['mpcheckoutpro:unenrol'] = 'Unenrol users from the course';
$string['mpcheckoutpro:unenrolself'] = 'Unenrol self from the course';
$string['mpcheckoutpro:viewtransactions'] = 'View Mercado Pago payment transactions';
$string['mpcheckoutpro:reconcile'] = 'Re-check a payment against the Mercado Pago API';

// -------------------------------------------------------------- Setting groups.
$string['settings_credentials'] = 'Mercado Pago credentials';
$string['settings_credentials_desc'] = 'Credentials come from <em>Your integrations</em> in the Mercado Pago developer dashboard. They can also be supplied from config.php as <code>$CFG->enrol_mpcheckoutpro</code> or from the <code>MPCHECKOUTPRO_ACCESS_TOKEN</code>, <code>MPCHECKOUTPRO_PUBLIC_KEY</code> and <code>MPCHECKOUTPRO_WEBHOOK_SECRET</code> environment variables, which take precedence over the values stored here.';
$string['settings_webhooks'] = 'Webhooks';
$string['settings_webhooks_desc'] = 'Mercado Pago signs every notification with the secret signature of your application. Keep signature validation enabled on production sites.';
$string['settings_preference'] = 'Checkout Pro preference';
$string['settings_preference_desc'] = 'Defaults applied to every payment preference created by this plugin. Individual courses can override some of them.';
$string['settings_marketplace'] = 'Split payments (marketplace)';
$string['settings_marketplace_desc'] = 'Split payments let a marketplace collect a commission on each sale. Register <code>{$a->redirecturi}</code> as the redirect URI of your Mercado Pago application before connecting any seller.';
$string['settings_behaviour'] = 'Enrolment behaviour';
$string['settings_diagnostics'] = 'Diagnostics and performance';

// ---------------------------------------------------------------- Credentials.
$string['environment'] = 'Environment';
$string['environment_desc'] = 'Which set of credentials to use. In the test environment the buyer is sent to the sandbox checkout and no real money moves.';
$string['environment_production'] = 'Production';
$string['environment_test'] = 'Test';
$string['accesstoken'] = 'Access token';
$string['accesstoken_desc'] = 'Production access token of your Mercado Pago application. Used as the Bearer token for every API call.';
$string['publickey'] = 'Public key';
$string['publickey_desc'] = 'Production public key of your Mercado Pago application.';
$string['webhooksecret'] = 'Webhook secret signature';
$string['webhooksecret_desc'] = 'The secret signature shown next to your webhook configuration in <em>Your integrations</em>. Without it, incoming notifications cannot be verified.';
$string['testaccesstoken'] = 'Test access token';
$string['testaccesstoken_desc'] = 'Access token of your test credentials, used when the environment is set to Test.';
$string['testpublickey'] = 'Test public key';
$string['testwebhooksecret'] = 'Test webhook secret signature';
$string['allowinstancecredentials'] = 'Allow per-course credentials';
$string['allowinstancecredentials_desc'] = 'Let each enrolment instance store its own Mercado Pago credentials. Useful when different departments collect into different accounts. Instance credentials are encrypted and are never included in course backups.';
$string['instancecredentials'] = 'Credentials for this course';
$string['instancecredentials_desc'] = 'Leave these empty to use the site credentials. Anything entered here is encrypted before it is stored and is never exported in a course backup.';
$string['keepcredentials'] = 'Keep the stored credentials';
$string['keepcredentials_help'] = 'This instance already has its own credentials. Leave this checked to keep them; uncheck it and save with the fields empty to delete them and fall back to the site credentials.';
$string['sdkversion'] = 'Mercado Pago PHP SDK version {$a} detected.';
$string['webhookurl_desc'] = 'Configure this URL as the webhook endpoint of your Mercado Pago application: <code>{$a}</code>';

// ------------------------------------------------------------------- Webhooks.
$string['requiresignature'] = 'Require a valid signature';
$string['requiresignature_desc'] = 'Reject any notification whose <code>x-signature</code> header cannot be verified. Turn this off only while debugging.';
$string['signaturetolerance'] = 'Signature timestamp tolerance';
$string['signaturetolerance_desc'] = 'Maximum accepted difference, in seconds, between the timestamp in the signature and the server clock. Set to 0 to skip the check.';
$string['deferwebhooks'] = 'Process notifications in the background';
$string['deferwebhooks_desc'] = 'Acknowledge each notification immediately and do the Mercado Pago API call in the scheduled task instead. Use this if your server cannot reliably answer within the five seconds Mercado Pago allows.';
$string['webhookratelimit'] = 'Webhook rate limit';
$string['webhookratelimit_desc'] = 'Maximum notifications accepted per minute per remote address. Set to 0 to disable.';
$string['checkoutratelimit'] = 'Checkout rate limit';
$string['checkoutratelimit_desc'] = 'Maximum payment preferences a single user may create per minute. Set to 0 to disable.';
$string['reconcilemaxattempts'] = 'Maximum reconciliation attempts';
$string['reconcilemaxattempts_desc'] = 'Stop re-checking a transaction against the API after this many attempts.';
$string['reconcilemaxage'] = 'Maximum reconciliation age';
$string['reconcilemaxage_desc'] = 'Stop re-checking transactions older than this.';

// ----------------------------------------------------------------- Preference.
$string['autoreturn'] = 'Return automatically';
$string['autoreturn_desc'] = 'Send <code>auto_return=approved</code> so the buyer is redirected back to Moodle automatically after an approved payment.';
$string['binarymode'] = 'Binary mode';
$string['binarymode_desc'] = 'When enabled, a payment can only be approved or rejected - never left pending. This reduces the approval rate, so leave it off unless you need it.';
$string['statementdescriptor'] = 'Statement descriptor';
$string['statementdescriptor_desc'] = 'Short text shown on the buyer\'s card statement. Letters, digits and spaces only, up to 22 characters.';
$string['preferenceexpiry'] = 'Preference validity';
$string['preferenceexpiry_desc'] = 'How long a payment link stays valid. Set to 0 to send no expiration dates.';
$string['installments'] = 'Maximum installments';
$string['installments_desc'] = 'Highest number of installments offered to the buyer. Set to 0 to let Mercado Pago decide.';
$string['installments_help'] = 'Maps to <code>payment_methods.installments</code> in the preference. Set to 0 for this course to use the site setting.';
$string['defaultinstallments'] = 'Preselected installments';
$string['defaultinstallments_desc'] = 'Number of installments preselected on the checkout. Set to 0 to preselect nothing.';
$string['defaultinstallments_help'] = 'Maps to <code>payment_methods.default_installments</code>. It can never be higher than the maximum installments.';
$string['excludedpaymenttypes'] = 'Excluded payment types';
$string['excludedpaymenttypes_desc'] = 'Payment types that will not be offered. The authoritative list for your account comes from <code>GET /v1/payment_methods</code>. Cash held in a Mercado Pago account cannot be excluded.';
$string['excludedpaymenttypes_help'] = 'Maps to <code>payment_methods.excluded_payment_types</code> in the preference.';
$string['excludedpaymentmethods'] = 'Excluded payment methods';
$string['excludedpaymentmethods_desc'] = 'Comma separated list of payment method ids that will not be offered, for example <code>master,amex</code>.';
$string['excludedpaymentmethods_help'] = 'Maps to <code>payment_methods.excluded_payment_methods</code> in the preference. Use the ids returned by <code>GET /v1/payment_methods</code>.';
$string['defaultpaymentmethodid'] = 'Preselected payment method';
$string['defaultpaymentmethodid_desc'] = 'Payment method id preselected on the checkout. Leave empty to preselect nothing.';
$string['defaultpaymentmethodid_help'] = 'Maps to <code>payment_methods.default_payment_method_id</code> in the preference.';
$string['custommetadata'] = 'Custom metadata';
$string['custommetadata_desc'] = 'One <code>key=value</code> pair per line. These are added to the <code>metadata</code> object of the preference and come back on the payment, which makes them useful for reconciliation in your accounting system. Never put personal data here.';
$string['custommetadata_help'] = 'One <code>key=value</code> pair per line. Keys are lower-cased and non-alphanumeric characters are replaced with underscores. The plugin always sends the Moodle site, course, user and transaction ids.';
$string['itemdescription'] = 'Item description';
$string['itemdescription_help'] = 'Shown to the buyer on the Mercado Pago checkout as the description of what they are paying for. Leave empty to use the course name.';
$string['itemdescription_default'] = 'Enrolment in {$a}';
$string['categoryid'] = 'Item category';
$string['categoryid_help'] = 'Mercado Pago item <code>category_id</code>. <code>learnings</code> is the category for courses and training.';

// ------------------------------------------------------------ Split payments.
$string['splitpayments'] = 'Split payments (marketplace)';
$string['marketplaceenabled'] = 'Enable split payments';
$string['marketplaceenabled_desc'] = 'Allow courses to collect into a connected seller account while your marketplace keeps a commission.';
$string['marketplaceclientid'] = 'Application client id';
$string['marketplaceclientid_desc'] = 'The client id (application number) of your Mercado Pago marketplace application.';
$string['marketplaceclientsecret'] = 'Application client secret';
$string['marketplaceclientsecret_desc'] = 'The client secret of your Mercado Pago marketplace application. Used only to exchange OAuth codes for seller tokens.';
$string['marketplacename'] = 'Marketplace identifier';
$string['marketplacename_desc'] = 'Optional value sent as the <code>marketplace</code> field of the preference.';
$string['splitenabled'] = 'Use split payments for this course';
$string['splitenabled_help'] = 'When enabled, the payment preference is created with the connected seller\'s access token and your commission travels in <code>marketplace_fee</code>.';
$string['marketplacefee'] = 'Marketplace commission';
$string['marketplacefee_help'] = 'Amount, in the course currency, that your marketplace keeps from each payment. It must be lower than the course price. Mercado Pago deducts its own commission first, then this one.';
$string['sellerid'] = 'Seller id';
$string['sellerid_help'] = 'The Mercado Pago user id of the seller that collects for this course. It is filled in automatically when you connect a seller.';
$string['sellerconnection'] = 'Seller account';
$string['connectseller'] = 'Connect a Mercado Pago seller';
$string['reconnectseller'] = 'Reconnect the Mercado Pago seller';
$string['sellerconnected'] = 'The Mercado Pago seller {$a} is now connected to this enrolment method.';

// -------------------------------------------------------- Enrolment behaviour.
$string['pendingholding'] = 'Hold a place for pending payments';
$string['pendingholding_desc'] = 'Create a suspended enrolment as soon as an offline payment (cash coupon, bank transfer) is generated, and activate it when the money is credited.';
$string['pendingholding_help'] = 'A suspended enrolment does not give access to the course, but it shows on the participants list so teachers can see who is in the middle of paying.';
$string['reversalaction'] = 'Action on refund or chargeback';
$string['reversalaction_desc'] = 'What to do with the enrolment when a payment is refunded, cancelled or charged back.';
$string['reversalaction_help'] = 'The enrolment is only touched when no other approved payment of the same user still covers this course.';
$string['reversalkeep'] = 'Keep the enrolment';
$string['reversalsuspend'] = 'Suspend the enrolment and remove the role';
$string['reversalunenrol'] = 'Unenrol the user';
$string['notifications'] = 'Send notifications';
$string['notifications_desc'] = 'Notify buyers about the outcome of their payment, and notify course staff about approvals and reversals.';
$string['notifications_help'] = 'Recipients can still turn individual notifications off in their own preferences.';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_desc'] = 'What to do when a paid enrolment period ends.';
$string['cleanupafter'] = 'Retention period';
$string['cleanupafter_desc'] = 'Abandoned checkouts and processed webhook log rows older than this are deleted. Transactions that produced a payment are always kept.';
$string['usesitedefault'] = 'Use the site default';

// ---------------------------------------------------------------- Diagnostics.
$string['debuglogging'] = 'Verbose logging';
$string['debuglogging_desc'] = 'Write a line to the server error log for every Mercado Pago API call. Credentials and personal data are redacted, but this is noisy - leave it off in production.';
$string['apitimeout'] = 'API connection timeout';
$string['apitimeout_desc'] = 'Seconds to wait when connecting to api.mercadopago.com.';
$string['apimaxretries'] = 'API retries';
$string['apimaxretries_desc'] = 'How many times the SDK retries a failed request before giving up.';
$string['integratorid'] = 'Integrator id';
$string['integratorid_desc'] = 'Optional integrator id supplied by Mercado Pago for partner tracking.';
$string['platformid'] = 'Platform id';
$string['platformid_desc'] = 'Optional platform id supplied by Mercado Pago for platform tracking.';

// ------------------------------------------------------------- Instance form.
$string['instancedescription'] = 'Short description';
$string['instancedescription_help'] = 'Shown under the enrolment method name on the enrolment methods page, so managers can tell several price options apart.';
$string['status'] = 'Allow Mercado Pago enrolments';
$string['status_desc'] = 'Whether new instances are enabled by default. An instance can only be enabled once valid credentials exist and the site is served over HTTPS.';
$string['status_help'] = 'When disabled the enrolment method stops accepting new payments; existing enrolments are unaffected.';
$string['cost'] = 'Enrolment fee';
$string['cost_help'] = 'The price of the course in the selected currency. It must be greater than zero for the method to be enabled.';
$string['currency'] = 'Currency';
$string['assignrole'] = 'Assign role';
$string['assigngroup'] = 'Add to group';
$string['assigngroup_help'] = 'The buyer is added to this group when the payment is approved. Group membership is not removed if the payment is later reversed.';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of the enrolment bought. Set to 0 for unlimited.';
$string['enrolperiod_help'] = 'How long the access bought lasts, counted from the moment the payment is approved. Leave empty for unlimited access.';
$string['enrolstartdate'] = 'Start date';
$string['enrolenddate'] = 'End date';
$string['maxenrolled'] = 'Maximum enrolled users';
$string['maxenrolled_help'] = 'Once this many users hold an active enrolment through this method, no new payment can be started. Set to 0 for no limit.';
$string['defaultrole'] = 'Default role';
$string['defaultrole_desc'] = 'Role given to users who pay through this method.';
$string['paymentbehaviour'] = 'Payment behaviour';
$string['advancedpreference'] = 'Checkout Pro advanced options';

// -------------------------------------------------------------- Enrolment page.
$string['paybutton'] = 'Pay with Mercado Pago';
$string['redirectnotice'] = 'You will be taken to Mercado Pago to complete the payment and brought back here afterwards.';
$string['installmentsavailable'] = 'Up to {$a} installments available.';
$string['installmentsx'] = '({$a} installments)';
$string['testmodenotice'] = 'This site is running Mercado Pago in test mode. No real money will be charged.';
$string['pendingpaymentnotice'] = 'You already have a payment being processed ({$a}). You will be enrolled automatically as soon as it is credited.';

// -------------------------------------------------------------- Result page.
$string['paymentresult'] = 'Payment result';
$string['result_approved_title'] = 'Payment approved';
$string['result_approved_body'] = 'Your payment was credited and you now have access to the course.';
$string['result_pending_title'] = 'Payment pending';
$string['result_pending_body'] = 'Mercado Pago has not credited this payment yet. You will be enrolled automatically as soon as it is - no further action is needed.';
$string['result_rejected_title'] = 'Payment not completed';
$string['result_rejected_body'] = 'The payment was not completed, so no enrolment was created. You can try again with another payment method.';
$string['result_unknown_title'] = 'Payment not started';
$string['result_unknown_body'] = 'Mercado Pago has not reported a payment for this checkout. If you did pay, wait a few minutes and reload this page.';
$string['result_review_title'] = 'Payment under review';
$string['result_review_body'] = 'This payment is being reviewed by Mercado Pago. The enrolment will be updated automatically when the review finishes.';
$string['keepthisreference'] = 'Keep the reference above if you need to contact support about this payment.';

// ----------------------------------------------------------------- Report.
$string['transactions'] = 'Mercado Pago transactions';
$string['paymentstatus'] = 'Payment status';
$string['paymentmethod'] = 'Payment method';
$string['paymentid'] = 'Payment id';
$string['externalreference'] = 'Reference';
$string['enrolmentstate'] = 'Enrolment';
$string['lastupdate'] = 'Last update';
$string['allstatuses'] = 'All statuses';
$string['deleteduser'] = 'Deleted user';
$string['testmode'] = 'Test';
$string['reconcilenow'] = 'Re-check with Mercado Pago';
$string['reconcileresult'] = 'Re-check finished: {$a}';

// ----------------------------------------------------------- Payment statuses.
$string['status_created'] = 'Checkout started';
$string['status_approved'] = 'Approved';
$string['status_authorized'] = 'Authorised';
$string['status_in_process'] = 'In process';
$string['status_pending'] = 'Pending';
$string['status_cancelled'] = 'Cancelled';
$string['status_charged_back'] = 'Charged back';
$string['status_in_mediation'] = 'In mediation';
$string['status_refunded'] = 'Refunded';
$string['status_rejected'] = 'Rejected';
$string['status_unknown'] = 'Unknown ({$a})';

$string['enrolmentstate_none'] = 'Not enrolled';
$string['enrolmentstate_pending'] = 'Holding place';
$string['enrolmentstate_active'] = 'Active';
$string['enrolmentstate_suspended'] = 'Suspended';
$string['enrolmentstate_unenrolled'] = 'Unenrolled';

// ------------------------------------------------------------- Payment types.
$string['paymenttype_credit_card'] = 'Credit card';
$string['paymenttype_debit_card'] = 'Debit card';
$string['paymenttype_prepaid_card'] = 'Prepaid card';
$string['paymenttype_ticket'] = 'Cash coupon (ticket)';
$string['paymenttype_bank_transfer'] = 'Bank transfer';
$string['paymenttype_atm'] = 'ATM';

// ------------------------------------------------------------------- Errors.
$string['error:apicall'] = 'The call to Mercado Pago failed ({$a->operation}, HTTP {$a->status}).';
$string['error:sdkmissing'] = 'The Mercado Pago PHP SDK is not available. Install it under enrol/mpcheckoutpro/vendor before using this enrolment method.';
$string['error:nocredentials'] = 'No Mercado Pago credentials are configured for this enrolment method.';
$string['error:httpsrequired'] = 'Mercado Pago requires HTTPS for the notification and return URLs. This site is not served over HTTPS.';
$string['error:nocost'] = 'No enrolment fee has been set for this enrolment method.';
$string['error:costnotnumeric'] = 'The enrolment fee must be a number.';
$string['error:costpositive'] = 'The enrolment fee must be greater than zero for this method to be enabled.';
$string['error:enrolenddate'] = 'The end date cannot be earlier than the start date.';
$string['error:unsupportedcurrency'] = 'Mercado Pago does not support the currency {$a} in the sites this plugin covers.';
$string['error:instancedisabled'] = 'This enrolment method is currently disabled.';
$string['error:mustbeloggedin'] = 'You must be logged in with a real account to pay for a course.';
$string['error:enrolmentnotopen'] = 'Enrolment for this course has not opened yet.';
$string['error:enrolmentclosed'] = 'Enrolment for this course is closed.';
$string['error:alreadyenrolled'] = 'You are already enrolled in this course through this method.';
$string['error:coursefull'] = 'This enrolment method has reached its maximum number of enrolled users.';
$string['error:ratelimited'] = 'Too many payment attempts in a short time. Please wait a minute and try again.';
$string['error:preferencefailed'] = 'The payment could not be created at Mercado Pago. Please try again in a few minutes.';
$string['error:noinitpoint'] = 'Mercado Pago did not return a checkout URL for this payment.';
$string['error:unknowntransaction'] = 'This payment transaction does not exist.';
$string['error:referencemismatch'] = 'The payment reference does not match this transaction.';
$string['error:notavailable'] = 'This enrolment method is not available right now. Please contact the course administrator.';
$string['error:installmentsrange'] = 'The maximum number of installments must be between 0 and 36.';
$string['error:definstallmentsrange'] = 'The preselected installments must be between 0 and the maximum number of installments.';
$string['error:feenotnumeric'] = 'The marketplace commission must be a number.';
$string['error:feetoolarge'] = 'The marketplace commission must be lower than the enrolment fee.';
$string['error:invalidmethodid'] = 'Payment method ids may only contain lowercase letters, digits and underscores.';
$string['error:marketplacedisabled'] = 'Split payments are not enabled on this site.';
$string['error:oauthdenied'] = 'The Mercado Pago seller did not authorise the connection ({$a}).';
$string['error:oauthincomplete'] = 'Mercado Pago did not return an authorisation code.';
$string['error:oauthstate'] = 'The authorisation response could not be matched to a request. Please start the connection again.';
$string['error:oauthexchange'] = 'The authorisation code could not be exchanged for a seller token.';

// ------------------------------------------------------------------- Events.
$string['event:preference_created'] = 'Checkout preference created';
$string['event:payment_approved'] = 'Payment approved';
$string['event:payment_reversed'] = 'Payment reversed';
$string['event:payment_updated'] = 'Payment status updated';
$string['event:webhook_received'] = 'Mercado Pago notification received';
$string['event:webhook_rejected'] = 'Mercado Pago notification rejected';

// -------------------------------------------------------------------- Tasks.
$string['task:reconcile_payments'] = 'Reconcile Mercado Pago payments';
$string['task:retry_webhooks'] = 'Retry Mercado Pago notifications';
$string['task:process_expirations'] = 'Process Mercado Pago enrolment expirations';
$string['task:cleanup_records'] = 'Clean up Mercado Pago records';

// ----------------------------------------------------------------- Messages.
$string['messageprovider:payment_approved'] = 'Your Mercado Pago payment was approved';
$string['messageprovider:payment_pending'] = 'Your Mercado Pago payment is pending';
$string['messageprovider:payment_failed'] = 'Your Mercado Pago payment did not go through';
$string['messageprovider:payment_reversed'] = 'Your Mercado Pago payment was reversed';
$string['messageprovider:teacher_notification'] = 'Mercado Pago payment activity in your courses';
$string['messageprovider:expiry_notification'] = 'Mercado Pago enrolment expiry notifications';

$string['message_payment_approved_subject'] = 'Payment approved: {$a->coursename}';
$string['message_payment_approved_body'] = 'Hi {$a->fullname},

Your payment of {$a->amount} for "{$a->coursename}" was approved and you are now enrolled.

Go to the course: {$a->courseurl}

Mercado Pago payment id: {$a->paymentid}';

$string['message_payment_pending_subject'] = 'Payment pending: {$a->coursename}';
$string['message_payment_pending_body'] = 'Hi {$a->fullname},

Your payment of {$a->amount} for "{$a->coursename}" has not been credited yet ({$a->status}).

You do not need to do anything else: you will be enrolled automatically as soon as Mercado Pago credits the payment.

Mercado Pago payment id: {$a->paymentid}';

$string['message_payment_failed_subject'] = 'Payment not completed: {$a->coursename}';
$string['message_payment_failed_body'] = 'Hi {$a->fullname},

Your payment of {$a->amount} for "{$a->coursename}" was not completed ({$a->status}).

No charge was made and you have not been enrolled. You can try again from the course enrolment page:
{$a->courseurl}';

$string['message_payment_reversed_subject'] = 'Payment reversed: {$a->coursename}';
$string['message_payment_reversed_body'] = 'Hi {$a->fullname},

The payment of {$a->amount} for "{$a->coursename}" was reversed ({$a->status}) on {$a->date}, so your access to the course has been withdrawn.

If you believe this is a mistake, please contact the course administrator quoting the payment id {$a->paymentid}.';

$string['message_staffapproved_subject'] = 'New paid enrolment: {$a->coursename}';
$string['message_staffapproved_body'] = '{$a->fullname} paid {$a->amount} for "{$a->coursename}" and has been enrolled.

Mercado Pago payment id: {$a->paymentid}
Date: {$a->date}';

$string['message_staffreversed_subject'] = 'Payment reversed: {$a->coursename}';
$string['message_staffreversed_body'] = 'The payment of {$a->amount} made by {$a->fullname} for "{$a->coursename}" was reversed ({$a->status}).

Mercado Pago payment id: {$a->paymentid}
Date: {$a->date}';

$string['expirymessageenrolledsubject'] = 'Mercado Pago enrolment expiry notification';
$string['expirymessageenrolledbody'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->course}\', paid through Mercado Pago, is due to expire on {$a->timeend}.

If you need help, please contact {$a->enroller}.';
$string['expirymessageenrollersubject'] = 'Mercado Pago enrolment expiry notification';
$string['expirymessageenrollerbody'] = 'Mercado Pago enrolments in the course \'{$a->course}\' will expire within the next {$a->threshold} for the following users:

{$a->users}

To change this, go to {$a->extendurl}';

// -------------------------------------------------------------------- Privacy.
$string['privacy:metadata:txn'] = 'Information about payments made through Mercado Pago Checkout Pro to enrol in a course.';
$string['privacy:metadata:txn:userid'] = 'The id of the user who made the payment.';
$string['privacy:metadata:txn:courseid'] = 'The id of the course the payment was for.';
$string['privacy:metadata:txn:externalreference'] = 'The reference this site sent to Mercado Pago to identify the payment.';
$string['privacy:metadata:txn:preferenceid'] = 'The id of the Mercado Pago payment preference.';
$string['privacy:metadata:txn:paymentid'] = 'The id of the Mercado Pago payment.';
$string['privacy:metadata:txn:status'] = 'The status of the payment.';
$string['privacy:metadata:txn:amount'] = 'The amount paid.';
$string['privacy:metadata:txn:currency'] = 'The currency of the payment.';
$string['privacy:metadata:txn:paymentmethodid'] = 'The payment method used.';
$string['privacy:metadata:txn:installments'] = 'The number of installments chosen.';
$string['privacy:metadata:txn:timecreated'] = 'When the checkout was started.';
$string['privacy:metadata:txn:timeapproved'] = 'When the payment was approved.';

$string['privacy:metadata:mercadopago'] = 'To take a payment, some data has to be sent to Mercado Pago.';
$string['privacy:metadata:mercadopago:email'] = 'The email address of the buyer, so Mercado Pago can identify them at the checkout.';
$string['privacy:metadata:mercadopago:firstname'] = 'The first name of the buyer.';
$string['privacy:metadata:mercadopago:lastname'] = 'The last name of the buyer.';
$string['privacy:metadata:mercadopago:external_reference'] = 'An internal reference that identifies the purchase on this site.';
$string['privacy:metadata:mercadopago:metadata'] = 'The internal Moodle site, course, user and transaction ids of the purchase.';
$string['privacy:metadata:mercadopago:item'] = 'The name and price of the course being purchased.';

// ------------------------------------------------------- Course welcome message.
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_desc'] = 'Default for new enrolment instances. Whether a welcome message is sent when a payment is approved, and who it appears to come from.';
$string['sendcoursewelcomemessage_help'] = 'The welcome message is sent once, when the payment is approved and the enrolment becomes active. It is not sent for a pending payment, and not sent again if a reversed payment is later reinstated.

Core\'s "From the key holder" option is not offered here: it resolves the sender through the self enrolment key holder capability, and a paid enrolment has no key holder.';

// --------------------------------------------------- Mercado Pago account only.
$string['walletpurchase'] = 'Require a Mercado Pago account';
$string['walletpurchase_desc'] = 'Sends <code>purpose=wallet_purchase</code> in the preference, so only buyers logged in to a Mercado Pago account can pay. That is what makes account money and saved cards available at the checkout. The trade-off is real: buyers without an account cannot pay at all, and cash coupons and bank transfer disappear. Leave this off to accept guests paying by card.';