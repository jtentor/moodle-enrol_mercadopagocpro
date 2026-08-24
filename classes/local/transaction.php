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
 * Persistence helpers for the enrol_mpcheckoutpro_txn table.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction
{

    /**
     * @var string Table name. 
     */
    public const TABLE = 'enrol_mpcheckoutpro_txn';

    /**
     * Create the local transaction row before the preference is created.
     *
     * The row is written first so that its id can be embedded in the signed
     * external_reference and in the back_urls.
     *
     * @param  \stdClass         $instance enrol instance
     * @param  \stdClass         $user     buyer
     * @param  instance_settings $settings
     * @return \stdClass the created record
     */
    public static function create(\stdClass $instance, \stdClass $user, instance_settings $settings): \stdClass
    {
        global $DB;

        $now = time();
        $record = (object)[
            'enrolid' => (int)$instance->id,
            'courseid' => (int)$instance->courseid,
            'userid' => (int)$user->id,
            'externalreference' => '',
            'status' => status::LOCAL_CREATED,
            'enrolmentstate' => status::ENROLMENT_NONE,
            'amount' => util::normalise_amount($settings->cost),
            'currency' => $settings->currency,
            'marketplacefee' => $settings->marketplaceenabled ? util::normalise_amount($settings->marketplacefee) : null,
            'sellerid' => $settings->sellerid !== '' ? $settings->sellerid : null,
            'installments' => $settings->installments ?: null,
            'livemode' => credentials::get_environment_setting() === credentials::ENV_PRODUCTION ? 1 : 0,
            'reconcileattempts' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timeexpires' => $settings->preferenceexpiry > 0 ? $now + $settings->preferenceexpiry : null,
        ];

        // A placeholder unique value keeps the unique index happy until the real
        // reference (which embeds the row id) can be computed.
        $record->externalreference = 'pending-' . bin2hex(random_bytes(16));
        $record->id = $DB->insert_record(self::TABLE, $record);

        $record->externalreference = util::build_external_reference(
            (int)$record->id,
            (int)$instance->id,
            (int)$user->id
        );
        $DB->set_field(self::TABLE, 'externalreference', $record->externalreference, ['id' => $record->id]);

        return $record;
    }

    /**
     * Load a transaction by id.
     *
     * @param  int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass
    {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Load a transaction by its external reference.
     *
     * @param  string $reference
     * @return \stdClass|null
     */
    public static function get_by_reference(string $reference): ?\stdClass
    {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['externalreference' => $reference]);
        return $record ?: null;
    }

    /**
     * Load the most recent transaction carrying a given Mercado Pago payment id.
     *
     * @param  string $paymentid
     * @return \stdClass|null
     */
    public static function get_by_payment_id(string $paymentid): ?\stdClass
    {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['paymentid' => $paymentid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Most recent reusable transaction for a user on an instance.
     *
     * A transaction is reusable while it is still `created`, its preference has not
     * expired and the amount and currency still match, so a user who clicks the
     * pay button twice is sent back to the same Mercado Pago checkout.
     *
     * @param  int               $enrolid
     * @param  int               $userid
     * @param  instance_settings $settings
     * @return \stdClass|null
     */
    public static function get_reusable(int $enrolid, int $userid, instance_settings $settings): ?\stdClass
    {
        global $DB;

        $records = $DB->get_records(
            self::TABLE, [
            'enrolid' => $enrolid,
            'userid' => $userid,
            'status' => status::LOCAL_CREATED,
            ], 'id DESC', '*', 0, 5
        );

        $now = time();
        foreach ($records as $record) {
            if (empty($record->preferenceid) || empty($record->initpoint)) {
                continue;
            }
            if (!empty($record->timeexpires) && $record->timeexpires <= $now + 60) {
                continue;
            }
            if (abs((float)$record->amount - util::normalise_amount($settings->cost)) > 0.001) {
                continue;
            }
            if ($record->currency !== $settings->currency) {
                continue;
            }
            return $record;
        }
        return null;
    }

    /**
     * All transactions of a user on an enrolment instance, newest first.
     *
     * @param  int $enrolid
     * @param  int $userid
     * @return \stdClass[]
     */
    public static function get_for_user(int $enrolid, int $userid): array
    {
        global $DB;
        return $DB->get_records(self::TABLE, ['enrolid' => $enrolid, 'userid' => $userid], 'id DESC');
    }

    /**
     * Update a transaction, always refreshing timemodified.
     *
     * @param  int   $id
     * @param  array $fields field => value
     * @return \stdClass the reloaded record
     */
    public static function update(int $id, array $fields): \stdClass
    {
        global $DB;

        $fields['id'] = $id;
        $fields['timemodified'] = time();
        $DB->update_record(self::TABLE, (object)$fields);

        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Record the preference returned by the API.
     *
     * @param  int    $id
     * @param  string $preferenceid
     * @param  string $initpoint
     * @param  array  $metadata
     * @return \stdClass
     */
    public static function set_preference(int $id, string $preferenceid, string $initpoint, array $metadata): \stdClass
    {
        return self::update(
            $id, [
            'preferenceid' => $preferenceid,
            'initpoint' => $initpoint,
            'metadata' => util::encode_for_storage($metadata, 8000),
            'lasterror' => null,
            ]
        );
    }

    /**
     * Transactions the reconciliation task should re-check.
     *
     * @param  int $limit       maximum rows to return
     * @param  int $maxattempts give up after this many reconciliation attempts
     * @param  int $maxage      ignore transactions older than this many seconds
     * @return \stdClass[]
     */
    public static function get_pending_for_reconciliation(int $limit, int $maxattempts, int $maxage): array
    {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(
            array_merge(status::transitional(), [status::IN_MEDIATION]),
            SQL_PARAMS_NAMED,
            'st'
        );
        $params['minage'] = time() - $maxage;
        $params['maxattempts'] = $maxattempts;

        $select = "status $insql AND timecreated >= :minage AND reconcileattempts < :maxattempts";

        return $DB->get_records_select(self::TABLE, $select, $params, 'timemodified ASC', '*', 0, $limit);
    }

    /**
     * Record a failed API interaction on a transaction.
     *
     * @param  int    $id
     * @param  string $message
     * @return void
     */
    public static function record_error(int $id, string $message): void
    {
        global $DB;
        $DB->update_record(
            self::TABLE, (object)[
            'id' => $id,
            'lasterror' => \core_text::substr($message, 0, 1000),
            'timemodified' => time(),
            ]
        );
    }

    /**
     * Increment the reconciliation counter.
     *
     * @param  int $id
     * @return void
     */
    public static function touch_reconcile(int $id): void
    {
        global $DB;
        $DB->execute(
            'UPDATE {' . self::TABLE . '} SET reconcileattempts = reconcileattempts + 1, timemodified = ? WHERE id = ?',
            [time(), $id]
        );
    }

    /**
     * Number of users currently holding an active enrolment granted by this instance.
     *
     * @param  int $enrolid
     * @return int
     */
    public static function count_active_enrolments(int $enrolid): int
    {
        global $DB;
        return $DB->count_records_select(
            'user_enrolments',
            'enrolid = :enrolid AND status = :status',
            ['enrolid' => $enrolid, 'status' => ENROL_USER_ACTIVE]
        );
    }
}
