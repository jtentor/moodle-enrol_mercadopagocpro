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

namespace enrol_mpcheckoutpro;

use enrol_mpcheckoutpro\local\instance_settings;
use enrol_mpcheckoutpro\local\payment_processor;
use enrol_mpcheckoutpro\local\status;
use enrol_mpcheckoutpro\local\transaction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/enrol/mpcheckoutpro/tests/helper_trait.php';

/**
 * Tests for the payment status to enrolment state machine.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mpcheckoutpro\local\payment_processor
 */
final class payment_processor_test extends \advanced_testcase
{

    use helper_trait;

    /**
     * @var \stdClass 
     */
    protected \stdClass $course;
    /**
     * @var \stdClass 
     */
    protected \stdClass $instance;
    /**
     * @var \stdClass 
     */
    protected \stdClass $user;
    /**
     * @var \stdClass 
     */
    protected \stdClass $txn;

    /**
     * Build a course, an instance, a buyer and a transaction ready to settle.
     *
     * @param  array $instancefields
     * @param  array $siteconfig
     * @return void
     */
    protected function prepare(array $instancefields = [], array $siteconfig = []): void
    {
        $this->setup_plugin();
        foreach ($siteconfig as $name => $value) {
            set_config($name, $value, 'enrol_mpcheckoutpro');
        }

        [$this->course, $this->instance] = $this->create_course_with_instance($instancefields);
        $this->user = $this->getDataGenerator()->create_user();

        $settings = instance_settings::from_instance($this->instance);
        $this->txn = transaction::create($this->instance, $this->user, $settings);
    }

    /**
     * Queue a payment response matching the prepared transaction.
     *
     * @param  array $overrides
     * @return void
     */
    protected function queue_payment(array $overrides = []): void
    {
        $this->mpclient->push_payment(
            array_merge(
                [
                'external_reference' => $this->txn->externalreference,
                'transaction_amount' => 100.00,
                'currency_id' => 'ARS',
                ], $overrides
            )
        );
    }

    /**
     * An approved payment enrols the buyer.
     *
     * @return void
     */
    public function test_approved_payment_enrols_the_user(): void
    {
        $this->prepare();
        $this->queue_payment(['status' => 'approved']);

        $result = (new payment_processor())->process_payment('1122334455', $this->txn);

        $this->assertTrue($result->is_handled());
        $context = \context_course::instance($this->course->id);
        $this->assertTrue(is_enrolled($context, $this->user, '', true));

        $reloaded = transaction::get((int)$this->txn->id);
        $this->assertSame(status::APPROVED, $reloaded->status);
        $this->assertSame(status::ENROLMENT_ACTIVE, $reloaded->enrolmentstate);
        $this->assertNotEmpty($reloaded->timeapproved);
        $this->assertSame('1122334455', $reloaded->paymentid);
    }

    /**
     * Processing the same approved payment twice does not enrol twice.
     *
     * @return void
     */
    public function test_approved_payment_is_idempotent(): void
    {
        global $DB;

        $this->prepare();
        $this->queue_payment(['status' => 'approved']);
        $processor = new payment_processor();
        $processor->process_payment('1122334455', $this->txn);

        $this->queue_payment(['status' => 'approved']);
        $processor->process_payment('1122334455', transaction::get((int)$this->txn->id));

        $count = $DB->count_records(
            'user_enrolments', [
            'enrolid' => $this->instance->id,
            'userid' => $this->user->id,
            ]
        );
        $this->assertSame(1, $count);
    }

    /**
     * A rejected payment leaves the user out of the course.
     *
     * @return void
     */
    public function test_rejected_payment_does_not_enrol(): void
    {
        $this->prepare();
        $this->queue_payment(['status' => 'rejected', 'status_detail' => 'cc_rejected_insufficient_amount']);

        (new payment_processor())->process_payment('1122334455', $this->txn);

        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user));

        $reloaded = transaction::get((int)$this->txn->id);
        $this->assertSame(status::REJECTED, $reloaded->status);
        $this->assertSame(status::ENROLMENT_NONE, $reloaded->enrolmentstate);
    }

    /**
     * A pending payment creates a suspended holding enrolment when configured to.
     *
     * @return void
     */
    public function test_pending_payment_creates_holding_enrolment(): void
    {
        global $DB;

        $this->prepare([], ['pendingholding' => 1]);
        $this->queue_payment(['status' => 'pending', 'status_detail' => 'pending_waiting_payment']);

        (new payment_processor())->process_payment('1122334455', $this->txn);

        $ue = $DB->get_record(
            'user_enrolments', [
            'enrolid' => $this->instance->id,
            'userid' => $this->user->id,
            ]
        );
        $this->assertNotFalse($ue);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);

        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user, '', true));

        $reloaded = transaction::get((int)$this->txn->id);
        $this->assertSame(status::ENROLMENT_PENDING, $reloaded->enrolmentstate);
    }

    /**
     * The holding enrolment becomes active when the money is credited.
     *
     * @return void
     */
    public function test_pending_payment_is_activated_on_approval(): void
    {
        $this->prepare([], ['pendingholding' => 1]);

        $this->queue_payment(['status' => 'pending']);
        $processor = new payment_processor();
        $processor->process_payment('1122334455', $this->txn);

        $this->queue_payment(['status' => 'approved']);
        $processor->process_payment('1122334455', transaction::get((int)$this->txn->id));

        $context = \context_course::instance($this->course->id);
        $this->assertTrue(is_enrolled($context, $this->user, '', true));
        $this->assertSame(status::ENROLMENT_ACTIVE, transaction::get((int)$this->txn->id)->enrolmentstate);
    }

    /**
     * A refund suspends the enrolment when that is the configured action.
     *
     * @return void
     */
    public function test_refund_suspends_the_enrolment(): void
    {
        global $DB;

        $this->prepare([], ['reversalaction' => instance_settings::REVERSAL_SUSPEND]);

        $this->queue_payment(['status' => 'approved']);
        $processor = new payment_processor();
        $processor->process_payment('1122334455', $this->txn);

        $this->queue_payment(['status' => 'refunded']);
        $processor->process_payment('1122334455', transaction::get((int)$this->txn->id));

        $ue = $DB->get_record(
            'user_enrolments', [
            'enrolid' => $this->instance->id,
            'userid' => $this->user->id,
            ]
        );
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $this->assertSame(status::ENROLMENT_SUSPENDED, transaction::get((int)$this->txn->id)->enrolmentstate);
    }

    /**
     * A chargeback can be configured to unenrol the user outright.
     *
     * @return void
     */
    public function test_chargeback_can_unenrol(): void
    {
        global $DB;

        $this->prepare([], ['reversalaction' => instance_settings::REVERSAL_UNENROL]);

        $this->queue_payment(['status' => 'approved']);
        $processor = new payment_processor();
        $processor->process_payment('1122334455', $this->txn);

        $this->queue_payment(['status' => 'charged_back']);
        $processor->process_payment('1122334455', transaction::get((int)$this->txn->id));

        $this->assertFalse(
            $DB->record_exists(
                'user_enrolments', [
                'enrolid' => $this->instance->id,
                'userid' => $this->user->id,
                ]
            )
        );
        $this->assertSame(status::ENROLMENT_UNENROLLED, transaction::get((int)$this->txn->id)->enrolmentstate);
    }

    /**
     * An approved payment for the wrong amount never grants access.
     *
     * @return void
     */
    public function test_underpayment_is_withheld(): void
    {
        $this->prepare();
        $this->queue_payment(['status' => 'approved', 'transaction_amount' => 1.00]);

        $result = (new payment_processor())->process_payment('1122334455', $this->txn);

        $this->assertFalse($result->is_handled());
        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user));

        $reloaded = transaction::get((int)$this->txn->id);
        $this->assertSame(status::ENROLMENT_NONE, $reloaded->enrolmentstate);
        $this->assertNotEmpty($reloaded->lasterror);
    }

    /**
     * An approved payment in the wrong currency never grants access.
     *
     * @return void
     */
    public function test_wrong_currency_is_withheld(): void
    {
        $this->prepare();
        $this->queue_payment(['status' => 'approved', 'currency_id' => 'BRL']);

        (new payment_processor())->process_payment('1122334455', $this->txn);

        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user));
    }

    /**
     * A payment carrying somebody else's external_reference is refused.
     *
     * @return void
     */
    public function test_reference_mismatch_is_ignored(): void
    {
        $this->prepare();
        $this->queue_payment(['status' => 'approved', 'external_reference' => 'mpcp-1-1-1-0000000000000000']);

        $result = (new payment_processor())->process_payment('1122334455', $this->txn);

        $this->assertFalse($result->is_handled());
        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user));
    }

    /**
     * The buyer is added to the configured group when the payment is approved.
     *
     * @return void
     */
    public function test_group_assignment(): void
    {
        $this->setup_plugin();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('mpcheckoutpro');
        $instanceid = $plugin->add_instance(
            $course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 100,
            'currency' => 'ARS',
            'roleid' => $studentrole->id,
            'customint1' => $group->id,
            ]
        );
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $this->course = $course;
        $this->instance = $instance;
        $this->user = $this->getDataGenerator()->create_user();
        $this->txn = transaction::create($instance, $this->user, instance_settings::from_instance($instance));

        $this->queue_payment(['status' => 'approved']);
        (new payment_processor())->process_payment('1122334455', $this->txn);

        $this->assertTrue(groups_is_member($group->id, $this->user->id));
    }

    /**
     * An enrolment cap stops an approved payment from creating an enrolment.
     *
     * @return void
     */
    public function test_enrolment_cap_is_enforced(): void
    {
        $this->prepare(['customint5' => 1]);

        // Fill the only seat with another buyer.
        $other = $this->getDataGenerator()->create_user();
        $othertxn = transaction::create($this->instance, $other, instance_settings::from_instance($this->instance));
        $this->mpclient->push_payment(
            [
            'external_reference' => $othertxn->externalreference,
            'status' => 'approved',
            ]
        );
        (new payment_processor())->process_payment('999', $othertxn);

        $this->queue_payment(['status' => 'approved']);
        $result = (new payment_processor())->process_payment('1122334455', $this->txn);

        $this->assertFalse($result->is_handled());
        $context = \context_course::instance($this->course->id);
        $this->assertFalse(is_enrolled($context, $this->user));
        $this->assertTrue(is_enrolled($context, $other, '', true));
    }
}
