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

namespace enrol_mpcheckoutpro\local;

use enrol_mpcheckoutpro\event\payment_approved;
use enrol_mpcheckoutpro\event\payment_reversed;
use enrol_mpcheckoutpro\event\payment_updated;

/**
 * Turns a Mercado Pago payment into an enrolment decision.
 *
 * The only trusted input is the payment resource returned by
 * GET /v1/payments/{id}. Query strings coming back from the browser and the body
 * of a webhook notification are treated purely as hints about *which* payment to
 * look up, never as evidence of its state.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_processor {

    /** @var api_client */
    protected api_client $client;

    /**
     * Constructor.
     *
     * @param api_client|null $client injected for testing; resolved per instance when null
     */
    public function __construct(?api_client $client = null) {
        if ($client !== null) {
            $this->client = $client;
        }
    }

    /**
     * Process a payment id against the transaction it belongs to.
     *
     * @param string|int $paymentid Mercado Pago payment id
     * @param \stdClass|null $hinttransaction transaction the caller believes this payment belongs to
     * @return processing_result
     */
    public function process_payment($paymentid, ?\stdClass $hinttransaction = null): processing_result {
        global $DB;

        $paymentid = (string)$paymentid;
        if ($paymentid === '' || !ctype_digit($paymentid)) {
            return processing_result::ignored('Payment id is not a positive integer.');
        }

        // Find the transaction to work with: either the caller's hint or, failing
        // that, one already carrying this payment id.
        $transaction = $hinttransaction ?? transaction::get_by_payment_id($paymentid);

        // Without a transaction there is nothing to resolve credentials with, so the
        // payment cannot be fetched. This is normal for notifications produced by
        // other integrations sharing the same Mercado Pago account.
        if ($transaction === null) {
            return processing_result::ignored('No local transaction matches this payment.');
        }

        $instance = $DB->get_record('enrol', ['id' => $transaction->enrolid, 'enrol' => 'mpcheckoutpro']);
        if (!$instance) {
            return processing_result::ignored('The enrolment instance no longer exists.');
        }

        $client = $this->get_client($instance);

        try {
            $payment = $client->get_payment($paymentid);
        } catch (api_exception $e) {
            transaction::record_error((int)$transaction->id, $e->getMessage());
            return processing_result::retry($e->getMessage(), $e->is_retryable());
        }

        return $this->apply_payment($instance, $transaction, $payment);
    }

    /**
     * Re-query and process the payment already recorded on a transaction.
     *
     * @param \stdClass $transaction
     * @return processing_result
     */
    public function reconcile(\stdClass $transaction): processing_result {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $transaction->enrolid, 'enrol' => 'mpcheckoutpro']);
        if (!$instance) {
            return processing_result::ignored('The enrolment instance no longer exists.');
        }

        if (!empty($transaction->paymentid)) {
            return $this->process_payment($transaction->paymentid, $transaction);
        }

        // No payment id yet: resolve it from the merchant order when we have one.
        if (!empty($transaction->merchantorderid)) {
            return $this->process_merchant_order($transaction->merchantorderid, $transaction);
        }

        return processing_result::ignored('Nothing to reconcile yet: the buyer has not paid.');
    }

    /**
     * Resolve a merchant_order notification to its payments and process each of them.
     *
     * @param string|int $merchantorderid
     * @param \stdClass|null $hinttransaction
     * @return processing_result
     */
    public function process_merchant_order($merchantorderid, ?\stdClass $hinttransaction = null): processing_result {
        global $DB;

        $merchantorderid = (string)$merchantorderid;
        if ($merchantorderid === '' || !ctype_digit($merchantorderid)) {
            return processing_result::ignored('Merchant order id is not a positive integer.');
        }

        $transaction = $hinttransaction;
        if ($transaction === null) {
            $records = $DB->get_records(transaction::TABLE, ['merchantorderid' => $merchantorderid], 'id DESC', '*', 0, 1);
            $transaction = $records ? reset($records) : null;
        }
        if ($transaction === null) {
            return processing_result::ignored('No local transaction matches this merchant order.');
        }

        $instance = $DB->get_record('enrol', ['id' => $transaction->enrolid, 'enrol' => 'mpcheckoutpro']);
        if (!$instance) {
            return processing_result::ignored('The enrolment instance no longer exists.');
        }

        $client = $this->get_client($instance);
        try {
            $order = $client->get_merchant_order($merchantorderid);
        } catch (api_exception $e) {
            return processing_result::retry($e->getMessage(), $e->is_retryable());
        }

        $payments = is_array($order->payments ?? null) ? $order->payments : [];
        if (!$payments) {
            return processing_result::ignored('The merchant order carries no payments yet.');
        }

        $last = processing_result::ignored('No payment of the merchant order could be processed.');
        foreach ($payments as $orderpayment) {
            $id = is_object($orderpayment) ? ($orderpayment->id ?? null) : ($orderpayment['id'] ?? null);
            if ($id === null) {
                continue;
            }
            $last = $this->process_payment((string)$id, $transaction);
            if ($last->is_handled()) {
                // Once one payment of the order settles the enrolment, stop.
                break;
            }
        }
        return $last;
    }

    /**
     * Apply an authoritative payment resource to a transaction.
     *
     * @param \stdClass $instance
     * @param \stdClass $transaction
     * @param object $payment payment resource returned by the SDK
     * @return processing_result
     */
    public function apply_payment(\stdClass $instance, \stdClass $transaction, object $payment): processing_result {
        $settings = instance_settings::from_instance($instance);

        // The payment must belong to this transaction. Mercado Pago echoes the
        // external_reference we sent, so a mismatch means the wrong payment.
        $reference = (string)($payment->external_reference ?? '');
        if ($reference !== '' && $reference !== (string)$transaction->externalreference) {
            util::log_error('Payment external_reference does not match the transaction', [
                'txnid' => $transaction->id,
                'paymentid' => $payment->id ?? null,
            ]);
            return processing_result::ignored('external_reference mismatch.');
        }

        $lock = enrolment_manager::get_lock((int)$transaction->id);
        if ($lock === null) {
            return processing_result::retry('Could not obtain the transaction lock.', true);
        }

        try {
            // Re-read inside the lock so concurrent callers see each other's writes.
            $transaction = transaction::get((int)$transaction->id) ?? $transaction;

            $newstatus = (string)($payment->status ?? '');
            $previousstatus = (string)$transaction->status;

            $fields = [
                'paymentid' => (string)($payment->id ?? $transaction->paymentid),
                'status' => $newstatus !== '' ? $newstatus : $previousstatus,
                'statusdetail' => \core_text::substr((string)($payment->status_detail ?? ''), 0, 64),
                'paymentmethodid' => \core_text::substr((string)($payment->payment_method_id ?? ''), 0, 64),
                'paymenttypeid' => \core_text::substr((string)($payment->payment_type_id ?? ''), 0, 64),
                'installments' => (int)($payment->installments ?? 0) ?: null,
                'livemode' => !empty($payment->live_mode) ? 1 : 0,
                'lastapipayload' => util::encode_for_storage($payment),
                'lasterror' => null,
            ];

            $order = $payment->order ?? null;
            if ($order !== null) {
                $orderid = is_object($order) ? ($order->id ?? null) : ($order['id'] ?? null);
                if ($orderid !== null) {
                    $fields['merchantorderid'] = (string)$orderid;
                }
            }

            // Amount check: never grant access for less money than the course costs.
            $paidamount = (float)($payment->transaction_amount ?? 0);
            $paidcurrency = (string)($payment->currency_id ?? '');
            $amountok = $this->amount_matches($paidamount, $paidcurrency, $transaction);

            if (!$amountok && in_array($newstatus, status::granting(), true)) {
                $fields['lasterror'] = 'Approved payment does not match the expected amount or currency.';
                util::log_error('Approved payment amount mismatch - enrolment withheld', [
                    'txnid' => $transaction->id,
                    'expected' => $transaction->amount . ' ' . $transaction->currency,
                    'received' => $paidamount . ' ' . $paidcurrency,
                ]);
                $transaction = transaction::update((int)$transaction->id, $fields);
                $this->trigger_updated($instance, $transaction, $previousstatus);
                return processing_result::ignored('Amount mismatch, enrolment withheld for manual review.');
            }

            if (in_array($newstatus, status::granting(), true)) {
                $fields['timeapproved'] = (int)($transaction->timeapproved ?: time());
            }

            $transaction = transaction::update((int)$transaction->id, $fields);

            $outcome = $this->apply_enrolment_decision($instance, $transaction, $settings, $newstatus, $previousstatus);

            return $outcome;
        } finally {
            $lock->release();
        }
    }

    /**
     * Decide and perform the enrolment action for the current payment status.
     *
     * @param \stdClass $instance
     * @param \stdClass $transaction
     * @param instance_settings $settings
     * @param string $newstatus
     * @param string $previousstatus
     * @return processing_result
     */
    protected function apply_enrolment_decision(
        \stdClass $instance,
        \stdClass $transaction,
        instance_settings $settings,
        string $newstatus,
        string $previousstatus,
    ): processing_result {

        $state = (string)$transaction->enrolmentstate;

        if (in_array($newstatus, status::granting(), true)) {
            if ($state !== status::ENROLMENT_ACTIVE) {
                if (!enrolment_manager::has_capacity($instance, $settings) && $state !== status::ENROLMENT_PENDING) {
                    util::log_error('Course is full, approved payment could not be enrolled', [
                        'txnid' => $transaction->id,
                    ]);
                    transaction::record_error((int)$transaction->id,
                        'Course reached its maximum number of enrolled users.');
                    $this->notify(payment_notifier::EVENT_FAILED, $instance, $transaction, $settings);
                    return processing_result::ignored('Course is full.');
                }
                enrolment_manager::activate($instance, $transaction, $settings);
                $transaction = transaction::update((int)$transaction->id,
                    ['enrolmentstate' => status::ENROLMENT_ACTIVE]);
                payment_approved::create_from_transaction($instance, $transaction)->trigger();
                $this->send_welcome_message($instance, $transaction, $settings);
                $this->notify(payment_notifier::EVENT_APPROVED, $instance, $transaction, $settings);
            }
            return processing_result::handled(status::ENROLMENT_ACTIVE, $newstatus);
        }

        if (in_array($newstatus, status::reversing(), true)) {
            $resultingstate = $state;
            if (in_array($state, [status::ENROLMENT_ACTIVE, status::ENROLMENT_PENDING], true)) {
                $resultingstate = enrolment_manager::revoke($instance, $transaction, $settings);
                $transaction = transaction::update((int)$transaction->id, ['enrolmentstate' => $resultingstate]);
                payment_reversed::create_from_transaction($instance, $transaction)->trigger();
                $this->notify(payment_notifier::EVENT_REVERSED, $instance, $transaction, $settings);
            }
            return processing_result::handled($resultingstate, $newstatus);
        }

        if (in_array($newstatus, [status::PENDING, status::IN_PROCESS, status::AUTHORIZED], true)) {
            if ($settings->pendingholding && $state === status::ENROLMENT_NONE) {
                enrolment_manager::hold($instance, $transaction, $settings);
                $transaction = transaction::update((int)$transaction->id,
                    ['enrolmentstate' => status::ENROLMENT_PENDING]);
            }
            if ($previousstatus !== $newstatus) {
                $this->trigger_updated($instance, $transaction, $previousstatus);
                $this->notify(payment_notifier::EVENT_PENDING, $instance, $transaction, $settings);
            }
            return processing_result::handled((string)$transaction->enrolmentstate, $newstatus);
        }

        if ($newstatus === status::REJECTED) {
            if ($previousstatus !== $newstatus) {
                $this->trigger_updated($instance, $transaction, $previousstatus);
                $this->notify(payment_notifier::EVENT_FAILED, $instance, $transaction, $settings);
            }
            return processing_result::handled((string)$transaction->enrolmentstate, $newstatus);
        }

        // in_mediation and anything undocumented: record it and leave the enrolment alone.
        if ($previousstatus !== $newstatus) {
            $this->trigger_updated($instance, $transaction, $previousstatus);
        }
        return processing_result::handled((string)$transaction->enrolmentstate, $newstatus);
    }

    /**
     * Whether the money actually paid matches what the course costs.
     *
     * @param float $paidamount
     * @param string $paidcurrency
     * @param \stdClass $transaction
     * @return bool
     */
    protected function amount_matches(float $paidamount, string $paidcurrency, \stdClass $transaction): bool {
        $expected = (float)$transaction->amount;
        if ($paidcurrency !== '' && strtoupper($paidcurrency) !== strtoupper((string)$transaction->currency)) {
            return false;
        }
        // A tolerance of one cent absorbs rounding on the Mercado Pago side.
        return ($paidamount + 0.01) >= $expected;
    }

    /**
     * Trigger the generic status change event.
     *
     * @param \stdClass $instance
     * @param \stdClass $transaction
     * @param string $previousstatus
     * @return void
     */
    protected function trigger_updated(\stdClass $instance, \stdClass $transaction, string $previousstatus): void {
        payment_updated::create_from_transaction($instance, $transaction, [
            'previousstatus' => $previousstatus,
        ])->trigger();
    }

    /**
     * Send the course welcome message, if the instance is configured to.
     *
     * Delegates to the core implementation, so the placeholders, the formats and
     * the "from" contact resolution behave exactly as in enrol_self.
     *
     * @param \stdClass $instance
     * @param \stdClass $transaction
     * @param instance_settings $settings
     * @return void
     */
    protected function send_welcome_message(
        \stdClass $instance,
        \stdClass $transaction,
        instance_settings $settings,
    ): void {
        if ((int)$settings->welcomemessage === ENROL_DO_NOT_SEND_EMAIL) {
            return;
        }
        try {
            $plugin = enrol_get_plugin('mpcheckoutpro');
            if (!$plugin) {
                return;
            }
            $plugin->send_course_welcome_message_to_user(
                instance: $instance,
                userid: (int)$transaction->userid,
                sendoption: (int)$settings->welcomemessage,
                message: $settings->welcomemessagetext,
                roleid: $settings->roleid ?: null,
            );
        } catch (\Throwable $e) {
            // A welcome message must never cost somebody their paid enrolment.
            util::log_error('Welcome message could not be sent: ' . $e->getMessage(), [
                'txnid' => $transaction->id,
            ]);
        }
    }

    /**
     * Send the configured notifications, never letting a messaging failure break
     * the enrolment.
     *
     * @param string $event
     * @param \stdClass $instance
     * @param \stdClass $transaction
     * @param instance_settings $settings
     * @return void
     */
    protected function notify(string $event, \stdClass $instance, \stdClass $transaction, instance_settings $settings): void {
        if (!$settings->notifications) {
            return;
        }
        try {
            (new payment_notifier())->send($event, $instance, $transaction);
        } catch (\Throwable $e) {
            util::log_error('Notification could not be sent: ' . $e->getMessage(), [
                'event' => $event,
                'txnid' => $transaction->id,
            ]);
        }
    }

    /**
     * Resolve the API client for an instance.
     *
     * @param \stdClass $instance
     * @return api_client
     */
    protected function get_client(\stdClass $instance): api_client {
        if (isset($this->client)) {
            return $this->client;
        }
        return new api_client(credentials::resolve($instance));
    }
}
