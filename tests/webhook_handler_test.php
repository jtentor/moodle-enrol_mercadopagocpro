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

use enrol_mercadopagocpro\local\instance_settings;
use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\webhook_handler;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/enrol/mercadopagocpro/tests/helper_trait.php';

/**
 * Tests for the webhook endpoint logic.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mercadopagocpro\local\webhook_handler
 */
final class webhook_handler_test extends \advanced_testcase
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
     * Prepare a course, an instance, a buyer and a transaction.
     *
     * @return void
     */
    protected function prepare(): void
    {
        $this->setup_plugin();
        [$this->course, $this->instance] = $this->create_course_with_instance();
        $this->user = $this->getDataGenerator()->create_user();
        $this->txn = transaction::create(
            $this->instance,
            $this->user,
            instance_settings::from_instance($this->instance)
        );
    }

    /**
     * The notification body and query string are folded into one normalised shape.
     *
     * @return void
     */
    public function test_extract_notification(): void
    {
        $this->resetAfterTest();
        $handler = new webhook_handler();

        $normalised = $handler->extract_notification(
            ['data.id' => '1122334455', 'type' => 'payment'],
            ['id' => 987, 'action' => 'payment.updated', 'live_mode' => true, 'data' => ['id' => '1122334455']]
        );

        $this->assertSame('1122334455', $normalised['dataid']);
        $this->assertSame('payment', $normalised['type']);
        $this->assertSame('payment.updated', $normalised['action']);
        $this->assertSame('987', $normalised['notificationid']);
        $this->assertTrue($normalised['livemode']);
    }

    /**
     * The legacy `topic` query parameter is understood too.
     *
     * @return void
     */
    public function test_extract_notification_legacy_topic(): void
    {
        $this->resetAfterTest();
        $handler = new webhook_handler();

        $normalised = $handler->extract_notification(
            ['topic' => 'merchant_order', 'id' => '99887766'],
            []
        );

        $this->assertSame('merchant_order', $normalised['type']);
        $this->assertSame('99887766', $normalised['dataid']);
    }

    /**
     * A correctly signed notification settles the payment.
     *
     * @return void
     */
    public function test_valid_signature_is_processed(): void
    {
        $this->prepare();
        $this->mpclient->push_payment(
            [
            'external_reference' => $this->txn->externalreference,
            'status' => 'approved',
            ]
        );

        $requestid = 'req-abc-123';
        $headers = [
            'x-request-id' => $requestid,
            'x-signature' => $this->build_signature('1122334455', $requestid, 'TEST-WEBHOOK-SECRET'),
        ];
        $query = ['data.id' => '1122334455', 'type' => 'payment', 'enrolid' => $this->instance->id];
        $body = json_encode(
            ['id' => 1, 'type' => 'payment', 'action' => 'payment.updated',
            'data' => ['id' => '1122334455']]
        );

        $response = (new webhook_handler())->handle($query, $headers, $body, '10.0.0.1');

        $this->assertSame(200, $response['status']);
        $reloaded = transaction::get((int)$this->txn->id);
        $this->assertSame(status::APPROVED, $reloaded->status);
        $this->assertSame(status::ENROLMENT_ACTIVE, $reloaded->enrolmentstate);
    }

    /**
     * A notification with a wrong signature is rejected with 401 and changes nothing.
     *
     * @return void
     */
    public function test_invalid_signature_is_rejected(): void
    {
        $this->prepare();

        $headers = [
            'x-request-id' => 'req-abc-123',
            'x-signature' => 'ts=' . time() . ',v1=' . str_repeat('0', 64),
        ];
        $query = ['data.id' => '1122334455', 'type' => 'payment', 'enrolid' => $this->instance->id];

        $response = (new webhook_handler())->handle($query, $headers, '{"type":"payment"}', '10.0.0.1');

        $this->assertSame(401, $response['status']);
        $this->assertSame(status::LOCAL_CREATED, transaction::get((int)$this->txn->id)->status);
    }

    /**
     * A notification with no signature at all is rejected while validation is enforced.
     *
     * @return void
     */
    public function test_missing_signature_is_rejected(): void
    {
        $this->prepare();

        $response = (new webhook_handler())->handle(
            ['data.id' => '1122334455', 'type' => 'payment', 'enrolid' => $this->instance->id],
            [],
            '{"type":"payment"}',
            '10.0.0.1'
        );

        $this->assertSame(401, $response['status']);
    }

    /**
     * Every notification, accepted or not, leaves an audit row behind.
     *
     * @return void
     */
    public function test_notifications_are_logged(): void
    {
        global $DB;

        $this->prepare();
        (new webhook_handler())->handle(
            ['data.id' => '1122334455', 'type' => 'payment', 'enrolid' => $this->instance->id],
            [],
            '{"type":"payment"}',
            '10.0.0.1'
        );

        $log = $DB->get_records(webhook_handler::LOG_TABLE);
        $this->assertCount(1, $log);
        $row = reset($log);
        $this->assertSame('payment', $row->type);
        $this->assertSame('1122334455', $row->dataid);
        $this->assertSame(webhook_handler::SIG_MISSING, $row->signaturestatus);
        $this->assertEquals(401, $row->httpstatus);
    }

    /**
     * A notification about a resource this site knows nothing about is accepted
     * and ignored, so Mercado Pago does not keep retrying it.
     *
     * @return void
     */
    public function test_unknown_resource_is_accepted_and_ignored(): void
    {
        $this->prepare();

        $requestid = 'req-zzz';
        $headers = [
            'x-request-id' => $requestid,
            'x-signature' => $this->build_signature('55555', $requestid, 'TEST-WEBHOOK-SECRET'),
        ];

        $response = (new webhook_handler())->handle(
            ['data.id' => '55555', 'type' => 'payment'],
            $headers,
            '{"type":"payment","data":{"id":"55555"}}',
            '10.0.0.1'
        );

        $this->assertSame(200, $response['status']);
    }

    /**
     * Notification types this plugin does not handle are acknowledged, not retried.
     *
     * @return void
     */
    public function test_unhandled_type_is_acknowledged(): void
    {
        $this->prepare();
        set_config('requiresignature', 0, 'enrol_mercadopagocpro');

        $response = (new webhook_handler())->handle(
            ['data.id' => '123', 'type' => 'subscription_preapproval'],
            [],
            '{"type":"subscription_preapproval"}',
            '10.0.0.1'
        );

        $this->assertSame(200, $response['status']);
    }

    /**
     * The endpoint refuses oversized bodies outright.
     *
     * @return void
     */
    public function test_oversized_body_is_refused(): void
    {
        $this->prepare();

        $response = (new webhook_handler())->handle([], [], str_repeat('x', 70000), '10.0.0.1');
        $this->assertSame(413, $response['status']);
    }

    /**
     * The rate limiter closes the endpoint once the configured burst is exceeded.
     *
     * @return void
     */
    public function test_rate_limit(): void
    {
        $this->prepare();
        set_config('webhookratelimit', 2, 'enrol_mercadopagocpro');
        set_config('requiresignature', 0, 'enrol_mercadopagocpro');

        $handler = new webhook_handler();
        $call = fn() => $handler->handle(['type' => 'payment'], [], '{"type":"payment"}', '10.0.0.9');

        $this->assertSame(200, $call()['status']);
        $this->assertSame(200, $call()['status']);
        $this->assertSame(429, $call()['status']);
    }
}
