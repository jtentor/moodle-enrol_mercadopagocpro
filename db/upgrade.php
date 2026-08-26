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
 * Upgrade steps for enrol_mercadopagocpro.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database.
 *
 * v1.0.0 is the first released version, so there is nothing to upgrade from yet.
 * Future steps are appended here following the standard Moodle pattern.
 *
 * @param  int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_enrol_mercadopagocpro_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();
    unset($dbman); // Placeholder: kept so future steps have the manager at hand.

    // Automatically generated Moodle v5.2.0 release upgrade line.
    // Put any upgrade step following this.

    return true;
}
