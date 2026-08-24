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
 * Creates the Checkout Pro preference and sends the buyer to Mercado Pago.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require __DIR__ . '/../../config.php';

use enrol_mpcheckoutpro\local\checkout_service;
use enrol_mpcheckoutpro\local\util;

$instanceid = required_param('instanceid', PARAM_INT);

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mpcheckoutpro'], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login();
require_sesskey();

$courseurl = new moodle_url('/enrol/index.php', ['id' => $course->id]);

$PAGE->set_context($context);
$PAGE->set_url(util::plugin_url('checkout.php', ['instanceid' => $instanceid]));
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'enrol_mpcheckoutpro'));
$PAGE->set_heading($course->fullname);

if (isguestuser()) {
    throw new moodle_exception('error:mustbeloggedin', 'enrol_mpcheckoutpro', $courseurl);
}

try {
    $result = (new checkout_service())->start($instance, $USER);
} catch (moodle_exception $e) {
    util::log_debug(
        'Checkout could not be started', [
        'instanceid' => $instanceid,
        'userid' => $USER->id,
        'message' => $e->getMessage(),
        ]
    );
    // The user gets a friendly page with the reason and a way back to the course.
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->continue_button($courseurl);
    echo $OUTPUT->footer();
    die();
}

// Everything is server side from here: the buyer is handed the init_point that
// Mercado Pago returned and nothing about the price travels through the browser.
redirect(new moodle_url($result['redirecturl']));
