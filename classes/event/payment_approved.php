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
 * A Mercado Pago payment was approved and the enrolment was activated.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_approved extends transaction_event_base
{

    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init()
    {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name()
    {
        return get_string('event:payment_approved', 'enrol_mpcheckoutpro');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description()
    {
        return "The Mercado Pago payment '{$this->other['paymentid']}' for the user with id "
            . "'{$this->relateduserid}' was approved and the enrolment in the course with id "
            . "'{$this->contextinstanceid}' is now active.";
    }
}
