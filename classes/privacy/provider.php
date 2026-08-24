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

namespace enrol_mpcheckoutpro\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_mpcheckoutpro\local\status;
use enrol_mpcheckoutpro\local\transaction;

/**
 * Privacy Subsystem implementation for enrol_mpcheckoutpro.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://moodledev.io/docs/5.2/apis/subsystems/privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{

    /**
     * Describe the personal data this plugin stores and sends to Mercado Pago.
     *
     * @param  collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection
    {
        $items->add_database_table(
            transaction::TABLE,
            [
                'userid' => 'privacy:metadata:txn:userid',
                'courseid' => 'privacy:metadata:txn:courseid',
                'externalreference' => 'privacy:metadata:txn:externalreference',
                'preferenceid' => 'privacy:metadata:txn:preferenceid',
                'paymentid' => 'privacy:metadata:txn:paymentid',
                'status' => 'privacy:metadata:txn:status',
                'amount' => 'privacy:metadata:txn:amount',
                'currency' => 'privacy:metadata:txn:currency',
                'paymentmethodid' => 'privacy:metadata:txn:paymentmethodid',
                'installments' => 'privacy:metadata:txn:installments',
                'timecreated' => 'privacy:metadata:txn:timecreated',
                'timeapproved' => 'privacy:metadata:txn:timeapproved',
            ],
            'privacy:metadata:txn'
        );

        $items->add_external_location_link(
            'mercadopago',
            [
                'email' => 'privacy:metadata:mercadopago:email',
                'firstname' => 'privacy:metadata:mercadopago:firstname',
                'lastname' => 'privacy:metadata:mercadopago:lastname',
                'external_reference' => 'privacy:metadata:mercadopago:external_reference',
                'metadata' => 'privacy:metadata:mercadopago:metadata',
                'item' => 'privacy:metadata:mercadopago:item',
            ],
            'privacy:metadata:mercadopago'
        );

        return $items;
    }

    /**
     * Course contexts where a user has payment transactions.
     *
     * @param  int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {" . transaction::TABLE . "} t
                  JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :contextlevel
                 WHERE t.userid = :userid";

        $contextlist->add_from_sql(
            $sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
            ]
        );

        return $contextlist;
    }

    /**
     * Users who have payment transactions in a given context.
     *
     * @param  userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist)
    {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . transaction::TABLE . '} WHERE courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export the transactions of the approved contexts.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist)
    {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records(
                transaction::TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $user->id,
                ], 'timecreated ASC'
            );

            if (!$records) {
                continue;
            }

            $data = [];
            foreach ($records as $record) {
                $data[] = (object)[
                    'externalreference' => $record->externalreference,
                    'preferenceid' => $record->preferenceid,
                    'paymentid' => $record->paymentid,
                    'status' => status::label($record->status),
                    'statusdetail' => $record->statusdetail,
                    'enrolmentstate' => $record->enrolmentstate,
                    'amount' => $record->amount,
                    'currency' => $record->currency,
                    'installments' => $record->installments,
                    'paymentmethodid' => $record->paymentmethodid,
                    'paymenttypeid' => $record->paymenttypeid,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timeapproved' => $record->timeapproved
                        ? transform::datetime($record->timeapproved)
                        : null,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'enrol_mpcheckoutpro')],
                (object)['transactions' => $data]
            );
        }
    }

    /**
     * Delete every transaction in a context.
     *
     * @param  \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }
        $DB->delete_records(transaction::TABLE, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete the transactions of one user.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist)
    {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records(
                transaction::TABLE, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
                ]
            );
        }
    }

    /**
     * Delete the transactions of a list of users in one context.
     *
     * @param  approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist)
    {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select(transaction::TABLE, "courseid = :courseid AND userid $insql", $params);
    }
}
