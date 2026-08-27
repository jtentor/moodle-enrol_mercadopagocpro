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

use enrol_mercadopagocpro\local\checkout_service;
use enrol_mercadopagocpro\local\credentials;
use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\transaction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/mercadopagocpro/tests/helper_trait.php');

/**
 * Tests for starting a checkout.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mercadopagocpro\local\checkout_service
 */
final class checkout_service_test extends \advanced_testcase
{

    use helper_trait;

    /**
     * A checkout writes a transaction, calls the API once and returns the init_point.
     *
     * @return void
     */
    public function test_start_creates_transaction_and_preference(): void
    {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->mpclient->push_preference('PREF-99', 'https://mp.test/checkout/PREF-99');

        $result = (new checkout_service())->start($instance, $user);

        $this->assertSame('https://mp.test/checkout/PREF-99', $result['redirecturl']);

        $txn = transaction::get((int)$result['transaction']->id);
        $this->assertSame('PREF-99', $txn->preferenceid);
        $this->assertSame(status::LOCAL_CREATED, $txn->status);
        $this->assertSame(100.0, (float)$txn->amount);
        $this->assertSame('ARS', $txn->currency);
        $this->assertStringStartsWith('mpcp-', $txn->externalreference);

        $this->assertCount(1, $this->mpclient->requests);
        $this->assertStringContainsString('/checkout/preferences', $this->mpclient->last_uri());
    }

    /**
     * Every preference creation carries an idempotency key tied to the transaction.
     *
     * @return void
     */
    public function test_idempotency_key_is_sent(): void
    {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->mpclient->push_preference();
        $result = (new checkout_service())->start($instance, $user);

        $headers = $this->mpclient->requests[0]['headers'];
        $joined = implode("\n", (array)$headers);
        $this->assertStringContainsString(
            'X-Idempotency-Key: enrol_mercadopagocpro-' . $result['transaction']->id,
            $joined
        );
    }

    /**
     * Clicking pay twice reuses the preference instead of creating a second one.
     *
     * @return void
     */
    public function test_second_start_reuses_the_preference(): void
    {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->mpclient->push_preference('PREF-1', 'https://mp.test/checkout/PREF-1');
        $service = new checkout_service();
        $first = $service->start($instance, $user);
        $second = $service->start($instance, $user);

        $this->assertEquals($first['transaction']->id, $second['transaction']->id);
        $this->assertSame($first['redirecturl'], $second['redirecturl']);
        $this->assertCount(1, $this->mpclient->requests);
    }

    /**
     * The buyer always goes to init_point, never to the legacy sandbox URL,
     * whatever the environment. Test mode is a matter of credentials, not URLs.
     *
     * @return void
     */
    public function test_test_environment_still_uses_init_point(): void
    {
        $this->setup_plugin();
        set_config('environment', credentials::ENV_TEST, 'enrol_mercadopagocpro');
        set_config('testaccesstoken', 'TEST-TOKEN', 'enrol_mercadopagocpro');

        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->mpclient->push_preference('PREF-T', 'https://mp.test/checkout/PREF-T');
        $result = (new checkout_service())->start($instance, $user);

        $this->assertSame('https://mp.test/checkout/PREF-T', $result['redirecturl']);
        $this->assertStringNotContainsString('sandbox', $result['redirecturl']);
        $this->assertEquals(0, transaction::get((int)$result['transaction']->id)->livemode);
    }

    /**
     * A disabled instance never reaches the API.
     *
     * @return void
     */
    public function test_disabled_instance_is_refused(): void
    {
        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance(['status' => ENROL_INSTANCE_DISABLED]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        (new checkout_service())->start($instance, $user);
    }

    /**
     * A free instance never reaches the API.
     *
     * @return void
     */
    public function test_zero_cost_is_refused(): void
    {
        $this->setup_plugin();
        set_config('cost', 0, 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance(['cost' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        (new checkout_service())->start($instance, $user);
    }

    /**
     * An already enrolled user is not sold the same course twice.
     *
     * @return void
     */
    public function test_already_enrolled_is_refused(): void
    {
        $this->setup_plugin();
        [$course, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugin = enrol_get_plugin('mercadopagocpro');
        $plugin->enrol_user($instance, $user->id, null, 0, 0, ENROL_USER_ACTIVE);
        unset($course);

        $this->expectException(\moodle_exception::class);
        (new checkout_service())->start($instance, $user);
    }

    /**
     * With no credentials configured the checkout stops before the API call.
     *
     * @return void
     */
    public function test_missing_credentials_is_refused(): void
    {
        $this->setup_plugin();
        set_config('accesstoken', '', 'enrol_mercadopagocpro');
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        (new checkout_service())->start($instance, $user);
    }

    /**
     * A failure at Mercado Pago is recorded on the transaction and surfaced.
     *
     * @return void
     */
    public function test_api_failure_is_recorded(): void
    {
        global $DB;

        $this->setup_plugin();
        [, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->mpclient->push(['message' => 'invalid_token'], 401);

        try {
            (new checkout_service())->start($instance, $user);
            $this->fail('An exception was expected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('Mercado Pago', $e->getMessage());
        }

        $records = $DB->get_records(transaction::TABLE);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertNotEmpty($record->lasterror);
        $this->assertEmpty($record->preferenceid);
    }
}
