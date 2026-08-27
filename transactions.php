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
 * Payment transaction report for a course.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use enrol_mercadopagocpro\local\payment_processor;
use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\util;
use enrol_mercadopagocpro\output\transactions_table;

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHAEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$txnid = optional_param('txn', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/mercadopagocpro:viewtransactions', $context);

$pageurl = util::plugin_url(
    'transactions.php',
    array_filter(
        [
        'courseid' => $courseid,
        'instanceid' => $instanceid ?: null,
        'userid' => $userid ?: null,
        'status' => $statusfilter ?: null,
        ]
    )
);

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_course($course);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('transactions', 'enrol_mercadopagocpro'));
$PAGE->set_heading($course->fullname);

// Manual reconciliation.
if ($action === 'reconcile' && $txnid > 0) {
    require_sesskey();
    require_capability('enrol/mercadopagocpro:reconcile', $context);

    $record = transaction::get($txnid);
    if ($record === null || (int)$record->courseid !== (int)$course->id) {
        throw new moodle_exception('error:unknowntransaction', 'enrol_mercadopagocpro', $pageurl);
    }

    $result = (new payment_processor())->reconcile($record);
    redirect(
        $pageurl,
        get_string('reconcileresult', 'enrol_mercadopagocpro', $result->message),
        null,
        $result->is_handled()
            ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_INFO
    );
}

$statusoptions = ['' => get_string('allstatuses', 'enrol_mercadopagocpro')];
foreach (array_merge([status::LOCAL_CREATED], status::all()) as $value) {
    $statusoptions[$value] = status::label($value);
}

$table = new transactions_table(
    'enrol_mercadopagocpro_transactions',
    $context,
    (int)$course->id,
    (int)$instanceid,
    (int)$userid,
    $statusfilter
);
$table->define_baseurl($pageurl);
$table->is_downloading($download, 'mercadopagocpro_transactions_' . $course->shortname, 'transactions');

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('transactions', 'enrol_mercadopagocpro'));

    // Simple status filter.
    echo html_writer::start_tag(
        'form',
        ['method' => 'get', 'action' => $pageurl->out_omit_querystring(),
        'class' => 'mb-3 d-flex gap-2 align-items-end flex-wrap']
    );
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    if ($instanceid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'instanceid', 'value' => $instanceid]);
    }
    echo html_writer::tag(
        'label',
        get_string('paymentstatus', 'enrol_mercadopagocpro'),
        ['for' => 'mpstatusfilter', 'class' => 'me-1']
    );
    echo html_writer::select($statusoptions, 'status', $statusfilter, false, ['id' => 'mpstatusfilter']);
    echo html_writer::empty_tag(
        'input',
        ['type' => 'submit', 'class' => 'btn btn-secondary',
        'value' => get_string('filter')]
    );
    echo html_writer::end_tag('form');

    echo $OUTPUT->render_from_template(
        'enrol_mercadopagocpro/summary',
        [
        'stats' => enrol_mercadopagocpro_summary_rows((int)$course->id, (int)$instanceid),
        ]
    );
}

$table->out(50, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}

/**
 * Aggregate counts and totals per payment status for the summary block.
 *
 * @param  int $courseid
 * @param  int $instanceid
 * @return array
 */
function enrol_mercadopagocpro_summary_rows(int $courseid, int $instanceid): array {
    global $DB;

    $where = 'courseid = :courseid';
    $params = ['courseid' => $courseid];
    if ($instanceid > 0) {
        $where .= ' AND enrolid = :enrolid';
        $params['enrolid'] = $instanceid;
    }

    $sql = "SELECT status, COUNT(1) AS total, SUM(amount) AS amount, MAX(currency) AS currency
              FROM {enrol_mercadopagocpro_txn}
             WHERE $where
          GROUP BY status
          ORDER BY status ASC";

    $rows = [];
    foreach ($DB->get_records_sql($sql, $params) as $record) {
        $rows[] = [
            'label' => status::label($record->status),
            'count' => (int)$record->total,
            'amount' => $record->currency . ' ' . format_float((float)$record->amount, 2),
            'isapproved' => $record->status === status::APPROVED,
        ];
    }
    return $rows;
}
