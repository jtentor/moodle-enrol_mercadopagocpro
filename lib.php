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

/**
 * Mercado Pago Checkout Pro enrolment plugin.
 *
 * The plugin class itself lives in classes/plugin.php following the Moodle 5.x
 * convention used by core enrolment plugins (see enrol/fee/classes/plugin.php).
 * This file is kept because lib.php is a required file for enrolment plugins and
 * it is the documented fallback location core uses when the class is not
 * autoloadable.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @var string Plugin component name. 
*/
define('ENROL_MPCHECKOUTPRO_COMPONENT', 'enrol_mpcheckoutpro');

/**
 * Action performed when a payment is refunded / charged back: do nothing. 
*/
define('ENROL_MPCHECKOUTPRO_REVERSAL_KEEP', 0);
/**
 * Action performed when a payment is refunded / charged back: suspend and remove roles. 
*/
define('ENROL_MPCHECKOUTPRO_REVERSAL_SUSPEND', 1);
/**
 * Action performed when a payment is refunded / charged back: unenrol. 
*/
define('ENROL_MPCHECKOUTPRO_REVERSAL_UNENROL', 2);
