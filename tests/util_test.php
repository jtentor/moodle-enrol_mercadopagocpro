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

use enrol_mercadopagocpro\local\util;

/**
 * Tests for the shared helpers.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mercadopagocpro\local\util
 */
final class util_test extends \advanced_testcase
{
    /**
     * A reference produced by the plugin round-trips back to its parts.
     *
     * @return void
     */
    public function test_external_reference_roundtrip(): void {
        $this->resetAfterTest();

        $reference = util::build_external_reference(42, 7, 13);
        $parsed = util::parse_external_reference($reference);

        $this->assertNotNull($parsed);
        $this->assertSame(42, $parsed['txnid']);
        $this->assertSame(7, $parsed['enrolid']);
        $this->assertSame(13, $parsed['userid']);
    }

    /**
     * Tampering with any part of the reference invalidates the signature.
     *
     * @return void
     */
    public function test_external_reference_rejects_tampering(): void {
        $this->resetAfterTest();

        $reference = util::build_external_reference(42, 7, 13);
        $parts = explode('-', $reference);

        // Point the reference at a different transaction, keeping the signature.
        $parts[3] = '43';
        $this->assertNull(util::parse_external_reference(implode('-', $parts)));

        // Break the signature itself.
        $parts[3] = '42';
        $parts[4] = str_repeat('0', 16);
        $this->assertNull(util::parse_external_reference(implode('-', $parts)));
    }

    /**
     * References from other systems are not accepted.
     *
     * @return void
     */
    public function test_external_reference_rejects_foreign_values(): void {
        $this->resetAfterTest();

        $this->assertNull(util::parse_external_reference(null));
        $this->assertNull(util::parse_external_reference(''));
        $this->assertNull(util::parse_external_reference('order-99'));
        $this->assertNull(util::parse_external_reference('mpcp-a-b-c-d'));
    }

    /**
     * Secrets and personal data never survive redaction.
     *
     * @return void
     */
    public function test_redact_removes_sensitive_values(): void {
        $this->resetAfterTest();

        $redacted = util::redact(
            [
            'id' => 1,
            'access_token' => 'APP_USR-secret',
            'payer' => [
                'email' => 'buyer@example.com',
                'identification' => ['type' => 'DNI', 'number' => '12345678'],
            ],
            'card' => ['first_six_digits' => '450995'],
            'transaction_amount' => 100,
            ]
        );

        $this->assertSame('(redacted)', $redacted['access_token']);
        $this->assertSame('(redacted)', $redacted['payer']['email']);
        $this->assertSame('(redacted)', $redacted['payer']['identification']);
        $this->assertSame('(redacted)', $redacted['card']);
        $this->assertSame(100, $redacted['transaction_amount']);
        $this->assertSame(1, $redacted['id']);
    }

    /**
     * Stored payloads are capped so a huge response cannot blow up the column.
     *
     * @return void
     */
    public function test_encode_for_storage_truncates(): void {
        $this->resetAfterTest();

        $json = util::encode_for_storage(['blob' => str_repeat('x', 5000)], 500);
        $this->assertLessThanOrEqual(500, strlen($json));
        $this->assertStringContainsString('truncated', $json);
    }

    /**
     * Amounts are rounded to the two decimals Mercado Pago expects.
     *
     * @return void
     */
    public function test_normalise_amount(): void {
        $this->assertSame(10.35, util::normalise_amount(10.3456));
        $this->assertSame(10.0, util::normalise_amount(9.999));
    }
}
