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

/**
 * Mercado Pago payment statuses and the enrolment states this plugin derives from them.
 *
 * The nine payment statuses below are the ones documented by Mercado Pago for the
 * Payments API used by Checkout Pro. Nothing outside this list is ever treated as a
 * known status; anything else is stored verbatim and left for manual review.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.ar/developers/en/docs/checkout-api-payments/response-handling/query-results
 */
final class status
{

    /**
     * @var string Local-only status: preference created, buyer has not paid yet. 
     */
    public const LOCAL_CREATED = 'created';

    /**
     * @var string Payment credited. 
     */
    public const APPROVED = 'approved';
    /**
     * @var string Payment authorised, awaiting capture. 
     */
    public const AUTHORIZED = 'authorized';
    /**
     * @var string Payment being processed / under review. 
     */
    public const IN_PROCESS = 'in_process';
    /**
     * @var string Waiting for the payer to complete an action (cash coupon, transfer). 
     */
    public const PENDING = 'pending';
    /**
     * @var string Cancelled by expiry, collector or payer. 
     */
    public const CANCELLED = 'cancelled';
    /**
     * @var string Chargeback opened against the payment. 
     */
    public const CHARGED_BACK = 'charged_back';
    /**
     * @var string Payment under dispute / mediation. 
     */
    public const IN_MEDIATION = 'in_mediation';
    /**
     * @var string Refunded by the collector or by Mercado Pago. 
     */
    public const REFUNDED = 'refunded';
    /**
     * @var string Declined. 
     */
    public const REJECTED = 'rejected';

    /**
     * @var string No enrolment action has been taken. 
     */
    public const ENROLMENT_NONE = 'none';
    /**
     * @var string A suspended holding enrolment exists while the payment settles. 
     */
    public const ENROLMENT_PENDING = 'pending';
    /**
     * @var string The user is actively enrolled because of this transaction. 
     */
    public const ENROLMENT_ACTIVE = 'active';
    /**
     * @var string The enrolment was suspended after a reversal. 
     */
    public const ENROLMENT_SUSPENDED = 'suspended';
    /**
     * @var string The enrolment was removed after a reversal. 
     */
    public const ENROLMENT_UNENROLLED = 'unenrolled';

    /**
     * Every payment status documented by Mercado Pago.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::APPROVED,
            self::AUTHORIZED,
            self::IN_PROCESS,
            self::PENDING,
            self::CANCELLED,
            self::CHARGED_BACK,
            self::IN_MEDIATION,
            self::REFUNDED,
            self::REJECTED,
        ];
    }

    /**
     * Statuses that grant access to the course.
     *
     * Only `approved` means the money was credited. `authorized` is an
     * authorisation hold that still needs capture, so it does not grant access.
     *
     * @return string[]
     */
    public static function granting(): array
    {
        return [self::APPROVED];
    }

    /**
     * Statuses where the payment may still become approved on its own.
     *
     * These are the ones a holding (suspended) enrolment can be created for and
     * the ones the reconciliation task keeps polling.
     *
     * @return string[]
     */
    public static function transitional(): array
    {
        return [
            self::LOCAL_CREATED,
            self::PENDING,
            self::IN_PROCESS,
            self::AUTHORIZED,
        ];
    }

    /**
     * Statuses that revoke a previously granted access.
     *
     * @return string[]
     */
    public static function reversing(): array
    {
        return [
            self::REFUNDED,
            self::CHARGED_BACK,
            self::CANCELLED,
        ];
    }

    /**
     * Statuses that will never change again and need no further polling.
     *
     * `in_mediation` is deliberately excluded: a mediation can end in either
     * direction, so those transactions keep being reconciled.
     *
     * @return string[]
     */
    public static function terminal(): array
    {
        return [
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::REFUNDED,
            self::CHARGED_BACK,
        ];
    }

    /**
     * Whether the given status is one Mercado Pago documents.
     *
     * @param  string|null $status
     * @return bool
     */
    public static function is_known(?string $status): bool
    {
        return $status !== null && in_array($status, self::all(), true);
    }

    /**
     * Human readable label for a status.
     *
     * @param  string|null $status
     * @return string
     */
    public static function label(?string $status): string
    {
        $status = (string)$status;
        if ($status === self::LOCAL_CREATED) {
            return get_string('status_created', 'enrol_mpcheckoutpro');
        }
        if (self::is_known($status)) {
            return get_string('status_' . $status, 'enrol_mpcheckoutpro');
        }
        return get_string('status_unknown', 'enrol_mpcheckoutpro', s($status));
    }
}
