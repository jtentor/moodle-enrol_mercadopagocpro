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
use enrol_mpcheckoutpro\local\preference_builder;
use enrol_mpcheckoutpro\local\transaction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/enrol/mpcheckoutpro/tests/helper_trait.php';

/**
 * Tests for the Checkout Pro preference body.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mpcheckoutpro\local\preference_builder
 */
final class preference_builder_test extends \advanced_testcase
{

    use helper_trait;

    /**
     * Build a preference body for a freshly created course and user.
     *
     * @param  array $instancefields
     * @param  array $siteconfig
     * @return array
     */
    protected function build(array $instancefields = [], array $siteconfig = []): array
    {
        global $DB;

        $this->setup_plugin();
        foreach ($siteconfig as $name => $value) {
            set_config($name, $value, 'enrol_mpcheckoutpro');
        }

        [$course, $instance] = $this->create_course_with_instance($instancefields);
        $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $user = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);

        $settings = instance_settings::from_instance($instance);
        $txn = transaction::create($instance, $user, $settings);

        return (new preference_builder($instance, $course, $user, $txn, $settings))->build();
    }

    /**
     * The mandatory parts of a Checkout Pro preference are always present.
     *
     * @return void
     */
    public function test_minimal_preference(): void
    {
        $request = $this->build();

        $this->assertCount(1, $request['items']);
        $item = $request['items'][0];
        $this->assertSame(1, $item['quantity']);
        $this->assertSame('ARS', $item['currency_id']);
        $this->assertSame(100.0, $item['unit_price']);
        $this->assertNotEmpty($item['title']);

        $this->assertSame('buyer@example.com', $request['payer']['email']);

        $this->assertArrayHasKey('success', $request['back_urls']);
        $this->assertArrayHasKey('pending', $request['back_urls']);
        $this->assertArrayHasKey('failure', $request['back_urls']);
        foreach ($request['back_urls'] as $url) {
            $this->assertStringStartsWith('https://', $url);
        }

        $this->assertSame('approved', $request['auto_return']);
        $this->assertStringContainsString('/enrol/mpcheckoutpro/webhook.php', $request['notification_url']);
        $this->assertStringContainsString('enrolid=', $request['notification_url']);
        $this->assertStringStartsWith('mpcp-', $request['external_reference']);
        $this->assertFalse($request['binary_mode']);
    }

    /**
     * Metadata carries the Moodle ids needed for reconciliation and nothing personal.
     *
     * @return void
     */
    public function test_metadata_contains_moodle_identifiers(): void
    {
        $request = $this->build();
        $metadata = $request['metadata'];

        $this->assertArrayHasKey('moodle_txn_id', $metadata);
        $this->assertArrayHasKey('moodle_enrol_id', $metadata);
        $this->assertArrayHasKey('moodle_course_id', $metadata);
        $this->assertArrayHasKey('moodle_user_id', $metadata);
        $this->assertSame('enrol_mpcheckoutpro', $metadata['moodle_component']);

        $flattened = implode(' ', array_map('strval', $metadata));
        $this->assertStringNotContainsString('buyer@example.com', $flattened);
    }

    /**
     * Payment method rules are only sent when they were configured.
     *
     * @return void
     */
    public function test_payment_methods_block(): void
    {
        $request = $this->build();
        $this->assertArrayNotHasKey('payment_methods', $request);

        $request = $this->build(
            ['customint2' => 6, 'customint7' => 3],
            ['excludedpaymenttypes' => 'ticket,atm', 'excludedpaymentmethods' => 'amex']
        );

        $block = $request['payment_methods'];
        $this->assertSame(6, $block['installments']);
        $this->assertSame(3, $block['default_installments']);
        $this->assertSame([['id' => 'ticket'], ['id' => 'atm']], $block['excluded_payment_types']);
        $this->assertSame([['id' => 'amex']], $block['excluded_payment_methods']);
    }

    /**
     * default_installments can never exceed the offered installments.
     *
     * @return void
     */
    public function test_default_installments_is_capped(): void
    {
        $request = $this->build(['customint2' => 3, 'customint7' => 12]);
        $this->assertSame(3, $request['payment_methods']['default_installments']);
    }

    /**
     * Expiration dates are sent in the ISO 8601 form Mercado Pago documents.
     *
     * @return void
     */
    public function test_expiration_dates(): void
    {
        $request = $this->build([], ['preferenceexpiry' => 3600]);

        $this->assertTrue($request['expires']);
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/';
        $this->assertMatchesRegularExpression($pattern, $request['expiration_date_from']);
        $this->assertMatchesRegularExpression($pattern, $request['expiration_date_to']);
        $this->assertGreaterThan($request['expiration_date_from'], $request['expiration_date_to']);
    }

    /**
     * The statement descriptor is stripped of anything Mercado Pago will not take.
     *
     * @return void
     */
    public function test_statement_descriptor_is_sanitised(): void
    {
        $request = $this->build([], ['statementdescriptor' => 'Uni*versity! Courses & More 2026']);
        $this->assertSame('University Courses  Mo', $request['statement_descriptor']);
        $this->assertLessThanOrEqual(22, strlen($request['statement_descriptor']));
    }

    /**
     * marketplace_fee is only present when split payments are on for the instance.
     *
     * @return void
     */
    public function test_marketplace_fee(): void
    {
        $request = $this->build(['customint8' => 1, 'customdec1' => 15]);
        $this->assertArrayNotHasKey('marketplace_fee', $request);

        $request = $this->build(
            ['customint8' => 1, 'customdec1' => 15],
            ['marketplaceenabled' => 1, 'marketplacename' => 'MOODLE-MP']
        );
        $this->assertSame(15.0, $request['marketplace_fee']);
        $this->assertSame('MOODLE-MP', $request['marketplace']);
    }

    /**
     * purpose is only sent when the site requires a Mercado Pago account.
     *
     * @return void
     */
    public function test_wallet_purchase_purpose(): void
    {
        $request = $this->build();
        $this->assertArrayNotHasKey('purpose', $request);

        $request = $this->build([], ['walletpurchase' => 1]);
        $this->assertSame('wallet_purchase', $request['purpose']);
    }
}
