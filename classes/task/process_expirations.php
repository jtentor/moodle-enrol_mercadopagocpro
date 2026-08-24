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

/**
 * Expires enrolments whose paid period ran out and sends expiry notifications.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_expirations extends \core\task\scheduled_task {

    /**
     * Task name shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:process_expirations', 'enrol_mpcheckoutpro');
    }

    /**
     * Run the expiration handling.
     *
     * @return void
     */
    public function execute() {
        $plugin = enrol_get_plugin('mpcheckoutpro');
        if (!$plugin) {
            return;
        }

        $trace = new \text_progress_trace();
        $plugin->sync($trace);
        $plugin->send_expiry_notifications($trace);
        $trace->finished();
    }
}
