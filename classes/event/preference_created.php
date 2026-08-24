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
 * A Checkout Pro preference was created for a user.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preference_created extends transaction_event_base {

    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'c';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:preference_created', 'enrol_mpcheckoutpro');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' started a Mercado Pago Checkout Pro payment "
            . "(preference '{$this->other['preferenceid']}') for the enrolment instance with id "
            . "'{$this->other['enrolid']}' in the course with id '{$this->contextinstanceid}'.";
    }
}
