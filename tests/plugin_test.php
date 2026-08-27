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

namespace enrol_mercadopagocpro;

use enrol_mercadopagocpro\local\credentials;
use enrol_mercadopagocpro\local\instance_settings;
use enrol_mercadopagocpro\local\transaction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/mercadopagocpro/tests/helper_trait.php');

/**
 * Tests for the enrolment plugin class itself.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mercadopagocpro_plugin
 */
final class plugin_test extends \advanced_testcase
{
    use helper_trait;

    /**
     * The plugin loads and exposes the expected defaults.
     *
     * @return void
     */
    public function test_basics(): void {
        $this->resetAfterTest();

        $plugin = enrol_get_plugin('mercadopagocpro');
        $this->assertInstanceOf(\enrol_mercadopagocpro_plugin::class, $plugin);
        $this->assertFalse($plugin->roles_protected());
        $this->assertArrayHasKey('ARS', $plugin->get_possible_currencies());
    }

    /**
     * An instance can be created and its fields survive the round trip.
     *
     * @return void
     */
    public function test_add_instance(): void {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance(
            [
            'cost' => '250.50',
            'customint2' => 6,
            'customchar1' => 'Full access',
            ]
        );

        $this->assertEquals(250.5, (float)$instance->cost);
        $this->assertEquals(6, $instance->customint2);
        $this->assertSame('Full access', $instance->customchar1);
    }

    /**
     * Advanced Checkout Pro options are folded into customtext2 and read back.
     *
     * @return void
     */
    public function test_advanced_options_roundtrip(): void {
        global $DB;

        $this->setup_plugin();
        [$course, $instance] = $this->create_course_with_instance();

        $plugin = enrol_get_plugin('mercadopagocpro');
        $data = (object)(array)$instance;
        $data->mpexcludedtypes = ['ticket', 'atm'];
        $data->mpexcludedmethods = 'amex, master';
        $data->mpitemdescription = 'One year of access';
        $data->mpcategoryid = 'learnings';
        $data->mpmetadata = "campaign=spring\ncost_centre=1234";
        $plugin->update_instance($instance, $data);

        $reloaded = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $settings = instance_settings::from_instance($reloaded);

        $this->assertSame(['ticket', 'atm'], $settings->excludedpaymenttypes);
        $this->assertSame(['amex', 'master'], $settings->excludedpaymentmethods);
        $this->assertSame('One year of access', $settings->itemdescription);
        $this->assertSame('spring', $settings->custommetadata['campaign']);
        $this->assertSame('1234', $settings->custommetadata['cost_centre']);
        unset($course);
    }

    /**
     * The instance form refuses configurations that cannot produce a valid payment.
     *
     * @return void
     */
    public function test_edit_instance_validation(): void {
        $this->setup_plugin();
        [$course, $instance] = $this->create_course_with_instance();
        $context = \context_course::instance($course->id);
        $plugin = enrol_get_plugin('mercadopagocpro');

        $base = [
            'name' => 'Payment',
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => '100',
            'currency' => 'ARS',
            'roleid' => $instance->roleid,
            'enrolperiod' => 0,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'customint1' => 0,
            'customint2' => 0,
            'customint5' => 0,
            'customint7' => 0,
        ];

        $this->assertSame([], $plugin->edit_instance_validation($base, [], $instance, $context));

        $errors = $plugin->edit_instance_validation(array_merge($base, ['cost' => 'free']), [], $instance, $context);
        $this->assertArrayHasKey('cost', $errors);

        $errors = $plugin->edit_instance_validation(array_merge($base, ['cost' => '0']), [], $instance, $context);
        $this->assertArrayHasKey('cost', $errors);

        $errors = $plugin->edit_instance_validation(
            array_merge($base, ['enrolstartdate' => 200, 'enrolenddate' => 100]),
            [],
            $instance,
            $context
        );
        $this->assertArrayHasKey('enrolenddate', $errors);

        $errors = $plugin->edit_instance_validation(array_merge($base, ['customint2' => 99]), [], $instance, $context);
        $this->assertArrayHasKey('customint2', $errors);

        $errors = $plugin->edit_instance_validation(
            array_merge($base, ['customint2' => 3, 'customint7' => 6]),
            [],
            $instance,
            $context
        );
        $this->assertArrayHasKey('customint7', $errors);

        $errors = $plugin->edit_instance_validation(
            array_merge($base, ['customint8' => 1, 'customdec1' => '250']),
            [],
            $instance,
            $context
        );
        $this->assertArrayHasKey('customdec1', $errors);

        $errors = $plugin->edit_instance_validation(
            array_merge($base, ['mpexcludedmethods' => 'AMEX!!']),
            [],
            $instance,
            $context
        );
        $this->assertArrayHasKey('mpexcludedmethods', $errors);
    }

    /**
     * Per instance credentials are encrypted at rest and resolved back correctly.
     *
     * @return void
     */
    public function test_instance_credentials(): void {
        global $DB;

        $this->setup_plugin();
        set_config('allowinstancecredentials', 1, 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance();

        credentials::store_for_instance((int)$instance->id, 'INSTANCE-TOKEN', 'INSTANCE-KEY', 'INSTANCE-SECRET');

        $stored = $DB->get_record(credentials::TABLE, ['enrolid' => $instance->id], '*', MUST_EXIST);
        $this->assertNotSame('INSTANCE-TOKEN', $stored->accesstoken);

        $resolved = credentials::resolve($instance);
        $this->assertSame('INSTANCE-TOKEN', $resolved->get_access_token());
        $this->assertSame('INSTANCE-SECRET', $resolved->get_webhook_secret());
        $this->assertSame('instance', $resolved->get_source());

        // Falls back to the site credentials once the override is removed.
        credentials::delete_for_instance((int)$instance->id);
        $this->assertSame('TEST-ACCESS-TOKEN', credentials::resolve($instance)->get_access_token());
    }

    /**
     * Instance credentials are ignored while the site setting forbids them.
     *
     * @return void
     */
    public function test_instance_credentials_can_be_forbidden(): void {
        $this->setup_plugin();
        set_config('allowinstancecredentials', 0, 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance();

        credentials::store_for_instance((int)$instance->id, 'INSTANCE-TOKEN');

        $this->assertSame('TEST-ACCESS-TOKEN', credentials::resolve($instance)->get_access_token());
    }

    /**
     * Deleting an instance removes its credentials but keeps its transactions.
     *
     * @return void
     */
    public function test_delete_instance_keeps_transactions(): void {
        global $DB;

        $this->setup_plugin();
        set_config('allowinstancecredentials', 1, 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();

        credentials::store_for_instance((int)$instance->id, 'INSTANCE-TOKEN');
        transaction::create($instance, $user, instance_settings::from_instance($instance));

        enrol_get_plugin('mercadopagocpro')->delete_instance($instance);

        $this->assertFalse($DB->record_exists(credentials::TABLE, ['enrolid' => $instance->id]));
        $this->assertSame(1, $DB->count_records(transaction::TABLE));
        $this->assertSame(0, $DB->count_records(transaction::TABLE, ['enrolid' => $instance->id]));
    }

    /**
     * The site settings are applied when the instance leaves a value unset.
     *
     * @return void
     */
    public function test_settings_fall_back_to_site_defaults(): void {
        $this->setup_plugin();
        set_config('installments', 12, 'enrol_mercadopagocpro');
        set_config('pendingholding', 1, 'enrol_mercadopagocpro');

        [, $instance] = $this->create_course_with_instance(['customint2' => 0, 'customint3' => -1]);
        $settings = instance_settings::from_instance($instance);

        $this->assertSame(12, $settings->installments);
        $this->assertTrue($settings->pendingholding);

        [, $instance] = $this->create_course_with_instance(['customint2' => 3, 'customint3' => 0]);
        $settings = instance_settings::from_instance($instance);

        $this->assertSame(3, $settings->installments);
        $this->assertFalse($settings->pendingholding);
    }

    /**
     * Expired enrolments are handled by the standard sync.
     *
     * @return void
     */
    public function test_sync_expires_enrolments(): void {
        global $DB;

        $this->setup_plugin();
        set_config('expiredaction', ENROL_EXT_REMOVED_SUSPENDNOROLES, 'enrol_mercadopagocpro');

        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('mercadopagocpro');

        $plugin->enrol_user(
            $instance,
            $user->id,
            $instance->roleid,
            time() - DAYSECS * 3,
            time() - DAYSECS,
            ENROL_USER_ACTIVE
        );

        $trace = new \null_progress_trace();
        $plugin->sync($trace);

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
    }

    /**
     * The welcome message columns follow the enrol_self convention.
     *
     * @return void
     */
    public function test_welcome_message_settings_roundtrip(): void {
        global $DB;

        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance(
            [
            'customint4' => ENROL_SEND_EMAIL_FROM_COURSE_CONTACT,
            'customtext1' => 'Hi {$a->firstname}, welcome to {$a->coursename}.',
            ]
        );

        $reloaded = $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
        $settings = instance_settings::from_instance($reloaded);

        $this->assertSame(ENROL_SEND_EMAIL_FROM_COURSE_CONTACT, $settings->welcomemessage);
        $this->assertStringContainsString('welcome to', $settings->welcomemessagetext);
    }

    /**
     * An approved payment sends the welcome message when one is configured.
     *
     * @return void
     */
    public function test_welcome_message_is_sent_on_approval(): void {
        $this->setup_plugin();
        [$course, $instance] = $this->create_course_with_instance(
            [
            'customint4' => ENROL_SEND_EMAIL_FROM_NOREPLY,
            'customtext1' => 'Welcome to {$a->coursename}!',
            ]
        );
        $user = $this->getDataGenerator()->create_user();
        $txn = transaction::create($instance, $user, instance_settings::from_instance($instance));

        $sink = $this->redirectMessages();
        $this->mpclient->push_payment(
            [
            'external_reference' => $txn->externalreference,
            'status' => 'approved',
            ]
        );
        (new \enrol_mercadopagocpro\local\payment_processor())->process_payment('1122334455', $txn);

        $welcome = array_values(
            $sink->get_messages_by_component_and_type(
                'moodle',
                'enrolcoursewelcomemessage'
            )
        );
        $this->assertCount(1, $welcome);
        $this->assertEquals($user->id, $welcome[0]->useridto);
        $this->assertStringContainsString(format_string($course->fullname), $welcome[0]->fullmessage);
    }

    /**
     * No welcome message is sent when the instance is set not to send one.
     *
     * @return void
     */
    public function test_welcome_message_can_be_disabled(): void {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance(['customint4' => ENROL_DO_NOT_SEND_EMAIL]);
        $user = $this->getDataGenerator()->create_user();
        $txn = transaction::create($instance, $user, instance_settings::from_instance($instance));

        $sink = $this->redirectMessages();
        $this->mpclient->push_payment(
            [
            'external_reference' => $txn->externalreference,
            'status' => 'approved',
            ]
        );
        (new \enrol_mercadopagocpro\local\payment_processor())->process_payment('1122334455', $txn);

        $welcome = $sink->get_messages_by_component_and_type('moodle', 'enrolcoursewelcomemessage');
        $this->assertCount(0, $welcome);
    }

    /**
     * A pending payment does not trigger the welcome message.
     *
     * @return void
     */
    public function test_welcome_message_not_sent_while_pending(): void {
        $this->setup_plugin();
        set_config('pendingholding', 1, 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance(
            [
            'customint4' => ENROL_SEND_EMAIL_FROM_NOREPLY,
            ]
        );
        $user = $this->getDataGenerator()->create_user();
        $txn = transaction::create($instance, $user, instance_settings::from_instance($instance));

        $sink = $this->redirectMessages();
        $this->mpclient->push_payment(
            [
            'external_reference' => $txn->externalreference,
            'status' => 'pending',
            ]
        );
        (new \enrol_mercadopagocpro\local\payment_processor())->process_payment('1122334455', $txn);

        $welcome = $sink->get_messages_by_component_and_type('moodle', 'enrolcoursewelcomemessage');
        $this->assertCount(0, $welcome);
    }
}
