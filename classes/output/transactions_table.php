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

namespace enrol_mercadopagocpro\output;

use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\util;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Transaction report table.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transactions_table extends \core_table\sql_table
{
    /**
     * @var \context_course
     */
    protected \context_course $context;

    /**
     * @var bool Whether the viewer may trigger a manual reconciliation.
     */
    protected bool $canreconcile;

    /**
     * Constructor.
     *
     * @param string          $uniqueid
     * @param \context_course $context
     * @param int             $courseid
     * @param int             $instanceid   0 for all instances of the course
     * @param int             $userid       0 for all users
     * @param string          $statusfilter empty for all statuses
     */
    public function __construct(
        string $uniqueid,
        \context_course $context,
        int $courseid,
        int $instanceid = 0,
        int $userid = 0,
        string $statusfilter = '',
    ) {
        parent::__construct($uniqueid);

        $this->context = $context;
        $this->canreconcile = has_capability('enrol/mercadopagocpro:reconcile', $context);

        $columns = ['timecreated', 'user', 'amount', 'status', 'enrolmentstate', 'paymentmethod', 'paymentid'];
        $headers = [
            get_string('date'),
            get_string('user'),
            get_string('cost', 'enrol_mercadopagocpro'),
            get_string('paymentstatus', 'enrol_mercadopagocpro'),
            get_string('enrolmentstate', 'enrol_mercadopagocpro'),
            get_string('paymentmethod', 'enrol_mercadopagocpro'),
            get_string('paymentid', 'enrol_mercadopagocpro'),
        ];
        if ($this->canreconcile) {
            $columns[] = 'actions';
            $headers[] = get_string('actions');
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('user');
        $this->no_sorting('paymentmethod');
        $this->no_sorting('actions');
        $this->collapsible(false);

        // The get_sql() call adds its own leading comma, so it is appended, not glued on.
        $userfields = \core_user\fields::for_name()->get_sql('u');

        $where = 't.courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($instanceid > 0) {
            $where .= ' AND t.enrolid = :enrolid';
            $params['enrolid'] = $instanceid;
        }
        if ($userid > 0) {
            $where .= ' AND t.userid = :userid';
            $params['userid'] = $userid;
        }
        if ($statusfilter !== '') {
            $where .= ' AND t.status = :statusfilter';
            $params['statusfilter'] = $statusfilter;
        }

        $this->set_sql(
            't.*, u.id AS uid, u.email' . $userfields->selects,
            '{enrol_mercadopagocpro_txn} t LEFT JOIN {user} u ON u.id = t.userid',
            $where,
            $params
        );
    }

    /**
     * Creation date column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated);
    }

    /**
     * User column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_user($row) {
        if (empty($row->uid)) {
            return get_string('deleteduser', 'enrol_mercadopagocpro');
        }
        $url = new \moodle_url('/user/view.php', ['id' => $row->uid, 'course' => $row->courseid]);
        return \html_writer::link($url, fullname($row));
    }

    /**
     * Amount column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_amount($row) {
        $text = $row->currency . ' ' . format_float((float)$row->amount, 2);
        if (!empty($row->marketplacefee)) {
            $text .= ' ' . \html_writer::tag(
                'small',
                '(' . get_string('marketplacefee', 'enrol_mercadopagocpro') . ': '
                . format_float((float)$row->marketplacefee, 2) . ')'
            );
        }
        if (empty($row->livemode)) {
            $text .= ' ' . \html_writer::tag(
                'span',
                get_string('testmode', 'enrol_mercadopagocpro'),
                ['class' => 'badge bg-warning text-dark']
            );
        }
        return $text;
    }

    /**
     * Payment status column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_status($row) {
        $classes = [
            status::APPROVED => 'bg-success',
            status::PENDING => 'bg-info',
            status::IN_PROCESS => 'bg-info',
            status::AUTHORIZED => 'bg-info',
            status::REJECTED => 'bg-danger',
            status::CANCELLED => 'bg-secondary',
            status::REFUNDED => 'bg-warning text-dark',
            status::CHARGED_BACK => 'bg-danger',
            status::IN_MEDIATION => 'bg-warning text-dark',
        ];
        $class = $classes[$row->status] ?? 'bg-secondary';
        $html = \html_writer::tag('span', status::label($row->status), ['class' => 'badge ' . $class]);
        if (!empty($row->statusdetail)) {
            $html .= ' ' . \html_writer::tag('small', s($row->statusdetail));
        }
        return $html;
    }

    /**
     * Enrolment state column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_enrolmentstate($row) {
        return get_string('enrolmentstate_' . $row->enrolmentstate, 'enrol_mercadopagocpro');
    }

    /**
     * Payment method column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_paymentmethod($row) {
        $parts = array_filter(
            [
            (string)$row->paymenttypeid,
            (string)$row->paymentmethodid,
            ]
        );
        $text = $parts ? s(implode(' / ', $parts)) : '-';
        if (!empty($row->installments) && $row->installments > 1) {
            $text .= ' ' . get_string('installmentsx', 'enrol_mercadopagocpro', $row->installments);
        }
        return $text;
    }

    /**
     * Payment id column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_paymentid($row) {
        $text = $row->paymentid ? s($row->paymentid) : '-';
        return $text . \html_writer::empty_tag('br')
            . \html_writer::tag('small', s($row->externalreference));
    }

    /**
     * Actions column.
     *
     * @param  \stdClass $row
     * @return string
     */
    public function col_actions($row) {
        global $OUTPUT;

        if (in_array($row->status, status::terminal(), true) && $row->status !== status::APPROVED) {
            return '';
        }
        $url = util::plugin_url(
            'transactions.php',
            [
            'courseid' => $row->courseid,
            'action' => 'reconcile',
            'txn' => $row->id,
            'sesskey' => sesskey(),
            ]
        );
        return $OUTPUT->action_icon(
            $url,
            new \pix_icon('t/reload', get_string('reconcilenow', 'enrol_mercadopagocpro'))
        );
    }
}
