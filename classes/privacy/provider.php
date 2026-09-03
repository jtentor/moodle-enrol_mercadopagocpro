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

namespace enrol_mercadopagocpro\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_mercadopagocpro\local\status;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\webhook_handler;

/**
 * Privacy Subsystem implementation for enrol_mercadopagocpro.
 *
 * Two tables hold data about an identifiable person. {@see transaction::TABLE}
 * records the purchase itself, keyed by userid. {@see webhook_handler::LOG_TABLE}
 * records every notification Mercado Pago sent about that purchase, keyed to the
 * transaction by txnid; its payload is redacted before storage but the row is
 * still about a specific person's payment, so it is declared, exported and
 * deleted alongside the transaction it belongs to.
 *
 * The third table, enrol_mercadopagocpro_cred, holds collecting-account
 * credentials attached to an enrolment instance. It is site configuration rather
 * than data about a Moodle user, so it is deliberately not declared here.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://moodledev.io/docs/5.2/apis/subsystems/privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /**
     * Describe the personal data this plugin stores and sends to Mercado Pago.
     *
     * Every field named here is also exported by {@see self::export_user_data()},
     * and every field exported there is named here. Keep the two in step.
     *
     * @param  collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            transaction::TABLE,
            [
                'userid' => 'privacy:metadata:txn:userid',
                'courseid' => 'privacy:metadata:txn:courseid',
                'externalreference' => 'privacy:metadata:txn:externalreference',
                'preferenceid' => 'privacy:metadata:txn:preferenceid',
                'paymentid' => 'privacy:metadata:txn:paymentid',
                'status' => 'privacy:metadata:txn:status',
                'statusdetail' => 'privacy:metadata:txn:statusdetail',
                'enrolmentstate' => 'privacy:metadata:txn:enrolmentstate',
                'amount' => 'privacy:metadata:txn:amount',
                'currency' => 'privacy:metadata:txn:currency',
                'paymentmethodid' => 'privacy:metadata:txn:paymentmethodid',
                'paymenttypeid' => 'privacy:metadata:txn:paymenttypeid',
                'installments' => 'privacy:metadata:txn:installments',
                'timecreated' => 'privacy:metadata:txn:timecreated',
                'timeapproved' => 'privacy:metadata:txn:timeapproved',
            ],
            'privacy:metadata:txn'
        );

        $items->add_database_table(
            webhook_handler::LOG_TABLE,
            [
                'txnid' => 'privacy:metadata:wh:txnid',
                'notificationid' => 'privacy:metadata:wh:notificationid',
                'requestid' => 'privacy:metadata:wh:requestid',
                'type' => 'privacy:metadata:wh:type',
                'action' => 'privacy:metadata:wh:action',
                'dataid' => 'privacy:metadata:wh:dataid',
                'signaturestatus' => 'privacy:metadata:wh:signaturestatus',
                'errormessage' => 'privacy:metadata:wh:errormessage',
                'payload' => 'privacy:metadata:wh:payload',
                'timecreated' => 'privacy:metadata:wh:timecreated',
            ],
            'privacy:metadata:wh'
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
     * Webhook log rows need no separate lookup: every one of them belongs to a
     * transaction, so they can only ever be in a context this query already
     * returns.
     *
     * @param  int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {" . transaction::TABLE . "} t
                  JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :contextlevel
                 WHERE t.userid = :userid";

        $contextlist->add_from_sql(
            $sql,
            [
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
    public static function get_users_in_context(userlist $userlist) {
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
     * Export the transactions of the approved contexts, and the notifications
     * received about them.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
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
                transaction::TABLE,
                [
                'courseid' => $context->instanceid,
                'userid' => $user->id,
                ],
                'timecreated ASC'
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

            $export = ['transactions' => $data];

            $notifications = self::export_webhook_logs(array_keys($records));
            if ($notifications) {
                $export['notifications'] = $notifications;
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'enrol_mercadopagocpro')],
                (object)$export
            );
        }
    }

    /**
     * Build the exportable form of the notifications received about a set of
     * transactions.
     *
     * @param  int[] $txnids
     * @return \stdClass[]
     */
    protected static function export_webhook_logs(array $txnids): array {
        global $DB;

        if (!$txnids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($txnids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            webhook_handler::LOG_TABLE,
            "txnid $insql",
            $params,
            'timecreated ASC'
        );

        $out = [];
        foreach ($records as $record) {
            $out[] = (object)[
                'notificationid' => $record->notificationid,
                'requestid' => $record->requestid,
                'type' => $record->type,
                'action' => $record->action,
                'dataid' => $record->dataid,
                'signaturestatus' => $record->signaturestatus,
                'errormessage' => $record->errormessage,
                'payload' => $record->payload,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }

        return $out;
    }

    /**
     * Delete every transaction in a context, and the notifications about them.
     *
     * @param  \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $txnids = $DB->get_fieldset_select(
            transaction::TABLE,
            'id',
            'courseid = :courseid',
            ['courseid' => $context->instanceid]
        );

        self::delete_webhook_logs($txnids);
        $DB->delete_records(transaction::TABLE, ['courseid' => $context->instanceid]);
    }

    /**
     * Delete the transactions of one user, and the notifications about them.
     *
     * @param  approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $conditions = [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ];

            $txnids = $DB->get_fieldset_select(
                transaction::TABLE,
                'id',
                'courseid = :courseid AND userid = :userid',
                $conditions
            );

            self::delete_webhook_logs($txnids);
            $DB->delete_records(transaction::TABLE, $conditions);
        }
    }

    /**
     * Delete the transactions of a list of users in one context, and the
     * notifications about them.
     *
     * @param  approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
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
        $select = "courseid = :courseid AND userid $insql";

        $txnids = $DB->get_fieldset_select(transaction::TABLE, 'id', $select, $params);

        self::delete_webhook_logs($txnids);
        $DB->delete_records_select(transaction::TABLE, $select, $params);
    }

    /**
     * Delete the webhook log rows belonging to a set of transactions.
     *
     * Always call this before deleting the transactions themselves: once the
     * transaction rows are gone there is nothing left to find their
     * notifications by, and the rows become unreachable rather than deleted.
     *
     * @param  int[] $txnids
     * @return void
     */
    protected static function delete_webhook_logs(array $txnids): void {
        global $DB;

        if (!$txnids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($txnids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(webhook_handler::LOG_TABLE, "txnid $insql", $params);
    }
}
