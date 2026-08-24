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

namespace enrol_mpcheckoutpro\event;

/**
 * Shared behaviour for the events raised about a payment transaction.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class transaction_event_base extends \core\event\base {

    /**
     * Common initialisation: read only, course level, on the transaction table.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'enrol_mpcheckoutpro_txn';
    }

    /**
     * Build an event from an enrolment instance and a transaction record.
     *
     * @param \stdClass $instance enrol instance
     * @param \stdClass $transaction transaction record
     * @param array $other extra values for the `other` payload
     * @return static
     */
    public static function create_from_transaction(\stdClass $instance, \stdClass $transaction, array $other = []): self {
        $event = static::create([
            'context' => \context_course::instance($instance->courseid),
            'objectid' => (int)$transaction->id,
            'relateduserid' => (int)$transaction->userid,
            'other' => array_merge([
                'enrolid' => (int)$instance->id,
                'status' => (string)$transaction->status,
                'statusdetail' => (string)($transaction->statusdetail ?? ''),
                'paymentid' => (string)($transaction->paymentid ?? ''),
                'preferenceid' => (string)($transaction->preferenceid ?? ''),
                'amount' => (float)$transaction->amount,
                'currency' => (string)$transaction->currency,
                'enrolmentstate' => (string)$transaction->enrolmentstate,
                'livemode' => (int)$transaction->livemode,
            ], $other),
        ]);
        $event->add_record_snapshot('enrol_mpcheckoutpro_txn', $transaction);
        return $event;
    }

    /**
     * URL of the transaction report for the course this event happened in.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/enrol/mpcheckoutpro/transactions.php', [
            'courseid' => $this->contextinstanceid,
            'txn' => $this->objectid,
        ]);
    }
}
