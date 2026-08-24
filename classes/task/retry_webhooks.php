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

use enrol_mpcheckoutpro\local\util;
use enrol_mpcheckoutpro\local\webhook_handler;

/**
 * Re-processes webhook notifications that were accepted but not settled at
 * reception time, either because the endpoint runs in deferred mode or because
 * the API call failed.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retry_webhooks extends \core\task\scheduled_task {

    /** @var int Maximum notifications handled in one run. */
    private const BATCH_SIZE = 50;

    /**
     * Task name shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:retry_webhooks', 'enrol_mpcheckoutpro');
    }

    /**
     * Run the retries.
     *
     * @return void
     */
    public function execute() {
        if (!enrol_is_enabled('mpcheckoutpro')) {
            mtrace('enrol_mpcheckoutpro is disabled, skipping webhook retries.');
            return;
        }

        $rows = webhook_handler::get_retryable(self::BATCH_SIZE);
        if (!$rows) {
            return;
        }

        mtrace('Retrying ' . count($rows) . ' Mercado Pago notification(s).');
        $handler = new webhook_handler();

        foreach ($rows as $row) {
            try {
                $result = $handler->retry($row);
                mtrace('  notification ' . $row->id . ': ' . $result->outcome . ' - ' . $result->message);
            } catch (\Throwable $e) {
                util::log_error('Webhook retry failed: ' . $e->getMessage(), ['logid' => $row->id]);
                mtrace('  notification ' . $row->id . ': error - ' . $e->getMessage());
            }
        }
    }
}
