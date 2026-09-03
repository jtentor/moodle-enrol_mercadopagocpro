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

namespace enrol_mercadopagocpro\event;

/**
 * A Mercado Pago payment was refunded, cancelled or charged back.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_reversed extends transaction_event_base
{
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:payment_reversed', 'enrol_mercadopagocpro');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description() {
        return "The Mercado Pago payment '{$this->other['paymentid']}' for the user with id "
            . "'{$this->relateduserid}' moved to '{$this->other['status']}'; the enrolment in the course "
            . "with id '{$this->contextinstanceid}' is now '{$this->other['enrolmentstate']}'.";
    }
}
