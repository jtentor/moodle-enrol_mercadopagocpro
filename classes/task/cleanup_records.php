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

namespace enrol_mpcheckoutpro\task;

use enrol_mpcheckoutpro\local\status;
use enrol_mpcheckoutpro\local\transaction;
use enrol_mpcheckoutpro\local\webhook_handler;

/**
 * Housekeeping: drops abandoned checkouts and ages out the webhook audit log.
 *
 * Transactions that produced a payment are never deleted here; they are financial
 * records. Only checkouts the buyer never completed and old notification rows go.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_records extends \core\task\scheduled_task {

    /**
     * Task name shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanup_records', 'enrol_mpcheckoutpro');
    }

    /**
     * Run the cleanup.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $keepfor = (int)get_config('enrol_mpcheckoutpro', 'cleanupafter');
        if ($keepfor <= 0) {
            mtrace('Retention is disabled, nothing to clean up.');
            return;
        }
        $cutoff = time() - $keepfor;

        // Abandoned checkouts: a preference was created but no payment ever appeared.
        $abandoned = $DB->count_records_select(
            transaction::TABLE,
            'status = :status AND paymentid IS NULL AND timecreated < :cutoff',
            ['status' => status::LOCAL_CREATED, 'cutoff' => $cutoff]
        );
        if ($abandoned > 0) {
            $DB->delete_records_select(
                transaction::TABLE,
                'status = :status AND paymentid IS NULL AND timecreated < :cutoff',
                ['status' => status::LOCAL_CREATED, 'cutoff' => $cutoff]
            );
            mtrace('Deleted ' . $abandoned . ' abandoned checkout(s).');
        }

        // Processed notification rows older than the retention period.
        $logs = $DB->count_records_select(
            webhook_handler::LOG_TABLE,
            'timecreated < :cutoff AND (processed = 1 OR attempts >= 5)',
            ['cutoff' => $cutoff]
        );
        if ($logs > 0) {
            $DB->delete_records_select(
                webhook_handler::LOG_TABLE,
                'timecreated < :cutoff AND (processed = 1 OR attempts >= 5)',
                ['cutoff' => $cutoff]
            );
            mtrace('Deleted ' . $logs . ' webhook log row(s).');
        }
    }
}
