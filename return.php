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
 * Landing page for the Checkout Pro back_urls.
 *
 * Mercado Pago appends payment_id, status, collection_id, collection_status,
 * external_reference, payment_type, merchant_order_id, preference_id, site_id,
 * processing_mode and merchant_account_id to this URL. None of those values are
 * trusted: the page always re-queries the API before deciding anything.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/configure-back-urls
 */

require(__DIR__ . '/../../config.php');

use enrol_mercadopagocpro\local\payment_processor;
use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\util;

$txnid = required_param('txn', PARAM_INT);
$result = optional_param('result', '', PARAM_ALPHA);

// Values sent by Mercado Pago. Used only as hints about which payment to read.
$paymentid = optional_param('payment_id', '', PARAM_ALPHANUM);
$collectionid = optional_param('collection_id', '', PARAM_ALPHANUM);
$merchantorderid = optional_param('merchant_order_id', '', PARAM_ALPHANUM);
$externalreference = optional_param('external_reference', '', PARAM_TEXT);

require_login();

$transaction = transaction::get($txnid);
if ($transaction === null) {
    throw new moodle_exception('error:unknowntransaction', 'enrol_mercadopagocpro');
}

// A user may only look at their own transaction.
if ((int)$transaction->userid !== (int)$USER->id) {
    $coursecontext = context_course::instance($transaction->courseid, IGNORE_MISSING);
    if (!$coursecontext || !has_capability('enrol/mercadopagocpro:viewtransactions', $coursecontext)) {
        throw new moodle_exception('nopermissions', 'error', '', 'view this transaction');
    }
}

// The signed external reference must match the transaction it claims to be.
if ($externalreference !== '') {
    $parsed = util::parse_external_reference($externalreference);
    if ($parsed === null || $parsed['txnid'] !== (int)$transaction->id) {
        util::log_error('Return URL carried a mismatched external_reference', ['txnid' => $txnid]);
        throw new moodle_exception('error:referencemismatch', 'enrol_mercadopagocpro');
    }
}

$course = $DB->get_record('course', ['id' => $transaction->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$enrolurl = new moodle_url('/enrol/index.php', ['id' => $course->id]);

$PAGE->set_context($context);
$PAGE->set_url(util::plugin_url('return.php', ['txn' => $txnid]));
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('paymentresult', 'enrol_mercadopagocpro'));
$PAGE->set_heading($course->fullname);

// Re-query the API. This is the step that actually decides the enrolment.
$processor = new payment_processor();
$lookupid = $paymentid !== '' ? $paymentid : $collectionid;

if ($lookupid !== '') {
    $processor->process_payment($lookupid, $transaction);
} else if ($merchantorderid !== '') {
    $processor->process_merchant_order($merchantorderid, $transaction);
} else {
    $processor->reconcile($transaction);
}

$transaction = transaction::get($txnid) ?? $transaction;

$isenrolled = is_enrolled($context, $USER, '', true);
$state = (string)$transaction->enrolmentstate;
$paymentstatus = (string)$transaction->status;

if (in_array($paymentstatus, status::granting(), true) && $state === status::ENROLMENT_ACTIVE) {
    $tone = 'success';
    $heading = get_string('result_approved_title', 'enrol_mercadopagocpro');
    $message = get_string('result_approved_body', 'enrol_mercadopagocpro');
    $continueurl = $courseurl;
} else if (in_array($paymentstatus, [status::PENDING, status::IN_PROCESS, status::AUTHORIZED], true)) {
    $tone = 'info';
    $heading = get_string('result_pending_title', 'enrol_mercadopagocpro');
    $message = get_string('result_pending_body', 'enrol_mercadopagocpro');
    $continueurl = $enrolurl;
} else if (in_array($paymentstatus, [status::REJECTED, status::CANCELLED], true)) {
    $tone = 'error';
    $heading = get_string('result_rejected_title', 'enrol_mercadopagocpro');
    $message = get_string('result_rejected_body', 'enrol_mercadopagocpro');
    $continueurl = $enrolurl;
} else if ($paymentstatus === status::LOCAL_CREATED) {
    // The buyer came back before Mercado Pago produced a payment.
    $tone = 'info';
    $heading = get_string('result_unknown_title', 'enrol_mercadopagocpro');
    $message = get_string('result_unknown_body', 'enrol_mercadopagocpro');
    $continueurl = $enrolurl;
} else {
    $tone = 'warning';
    $heading = get_string('result_review_title', 'enrol_mercadopagocpro');
    $message = get_string('result_review_body', 'enrol_mercadopagocpro');
    $continueurl = $enrolurl;
}

unset($result);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $OUTPUT->render_from_template(
    'enrol_mercadopagocpro/status_page',
    [
    'tone' => $tone,
    'message' => $message,
    'statuslabel' => status::label($paymentstatus),
    'paymentid' => (string)($transaction->paymentid ?? ''),
    'reference' => (string)$transaction->externalreference,
    'amount' => format_float((float)$transaction->amount, 2) . ' ' . $transaction->currency,
    'date' => userdate($transaction->timemodified),
    'isenrolled' => $isenrolled,
    'coursename' => format_string($course->fullname, true, ['context' => $context]),
    ]
);
echo $OUTPUT->continue_button($continueurl);
echo $OUTPUT->footer();
