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

namespace enrol_mercadopagocpro;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_mercadopagocpro\local\instance_settings;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\webhook_handler;
use enrol_mercadopagocpro\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/mercadopagocpro/tests/helper_trait.php');

/**
 * Tests for the privacy provider.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \enrol_mercadopagocpro\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase
{
    use helper_trait;

    /**
     * Create a course with a transaction for a user.
     *
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass}
     */
    protected function create_transaction(): array {
        $this->setup_plugin();
        [$course, $instance] = $this->create_course_with_instance();
        $user = $this->getDataGenerator()->create_user();
        transaction::create($instance, $user, instance_settings::from_instance($instance));
        return [$course, $instance, $user];
    }

    /**
     * Insert a webhook log row belonging to a transaction.
     *
     * @param  int $txnid
     * @return int Id of the new row.
     */
    protected function create_webhook_log(int $txnid): int {
        global $DB;

        return (int)$DB->insert_record(
            webhook_handler::LOG_TABLE,
            (object)[
                'txnid' => $txnid,
                'notificationid' => 'notif-' . $txnid,
                'requestid' => 'req-' . $txnid,
                'type' => 'payment',
                'action' => 'payment.updated',
                'dataid' => 'data-' . $txnid,
                'signaturestatus' => 'valid',
                'httpstatus' => 200,
                'processed' => 1,
                'attempts' => 1,
                'payload' => '{"redacted":true}',
                'timecreated' => time(),
                'timeprocessed' => time(),
            ]
        );
    }

    /**
     * The metadata describes the table and the external transfer.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('enrol_mercadopagocpro'));
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $tables = [];
        $external = [];
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tables[] = $item->get_name();
            }
            if ($item instanceof \core_privacy\local\metadata\types\external_location) {
                $external[] = $item->get_name();
            }
        }

        // Both tables that hold data about an identifiable person are declared.
        $this->assertContains(transaction::TABLE, $tables);
        $this->assertContains(webhook_handler::LOG_TABLE, $tables);

        // Data leaves the site, so the external location has to be declared too.
        $this->assertContains('mercadopago', $external);
    }

    /**
     * Every field the export emits is declared in the metadata.
     *
     * These two drifted apart once: the export was emitting statusdetail,
     * enrolmentstate and paymenttypeid while get_metadata() named twelve fields
     * and not those three. This test is what stops that recurring.
     *
     * @return void
     */
    public function test_exported_fields_are_declared(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('enrol_mercadopagocpro'));

        $declared = [];
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table
                && $item->get_name() === transaction::TABLE) {
                $declared = array_keys($item->get_privacy_fields());
            }
        }

        [$course, , $user] = $this->create_transaction();
        $context = \context_course::instance($course->id);
        provider::export_user_data(
            new approved_contextlist($user, 'enrol_mercadopagocpro', [$context->id])
        );

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'enrol_mercadopagocpro')]);
        $exported = array_keys((array)$data->transactions[0]);

        $this->assertEmpty(
            array_diff($exported, $declared),
            'Exported transaction fields that get_metadata() does not declare: '
                . implode(', ', array_diff($exported, $declared))
        );
    }

    /**
     * A buyer's course context is reported.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        [$course, , $user] = $this->create_transaction();

        $contextlist = provider::get_contexts_for_userid((int)$user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(
            \context_course::instance($course->id)->id,
            $contextlist->get_contextids()[0]
        );
    }

    /**
     * Buyers are found from a course context.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        [$course, , $user] = $this->create_transaction();

        $context = \context_course::instance($course->id);
        $userlist = new userlist($context, 'enrol_mercadopagocpro');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([(int)$user->id], $userlist->get_userids());
    }

    /**
     * Transactions are exported for the buyer.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        [$course, , $user] = $this->create_transaction();
        $context = \context_course::instance($course->id);

        provider::export_user_data(
            new approved_contextlist(
                $user,
                'enrol_mercadopagocpro',
                [$context->id]
            )
        );

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'enrol_mercadopagocpro')]);
        $this->assertCount(1, $data->transactions);
    }

    /**
     * Notifications about a transaction are exported with it.
     *
     * @return void
     */
    public function test_export_includes_webhook_logs(): void {
        global $DB;

        [$course, , $user] = $this->create_transaction();
        $txnid = (int)$DB->get_field(transaction::TABLE, 'id', ['userid' => $user->id]);
        $this->create_webhook_log($txnid);

        $context = \context_course::instance($course->id);
        provider::export_user_data(
            new approved_contextlist($user, 'enrol_mercadopagocpro', [$context->id])
        );

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'enrol_mercadopagocpro')]);
        $this->assertCount(1, $data->notifications);
        $this->assertSame('payment.updated', $data->notifications[0]->action);
    }

    /**
     * Deleting a context removes every transaction in it.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        [$course, , $user] = $this->create_transaction();
        $txnid = (int)$DB->get_field(transaction::TABLE, 'id', ['userid' => $user->id]);
        $this->create_webhook_log($txnid);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertSame(0, $DB->count_records(transaction::TABLE));
        // The notifications must go with the transactions, not be orphaned by them.
        $this->assertSame(0, $DB->count_records(webhook_handler::LOG_TABLE));
    }

    /**
     * Deleting one user removes only their transactions.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        [$course, $instance, $user] = $this->create_transaction();
        $other = $this->getDataGenerator()->create_user();
        $othertxn = transaction::create($instance, $other, instance_settings::from_instance($instance));

        $txnid = (int)$DB->get_field(transaction::TABLE, 'id', ['userid' => $user->id]);
        $this->create_webhook_log($txnid);
        $this->create_webhook_log((int)$othertxn->id);

        provider::delete_data_for_user(
            new approved_contextlist(
                $user,
                'enrol_mercadopagocpro',
                [\context_course::instance($course->id)->id]
            )
        );

        $this->assertSame(0, $DB->count_records(transaction::TABLE, ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records(transaction::TABLE, ['userid' => $other->id]));

        // Only the erased user's notifications go; the other user's stay.
        $this->assertSame(0, $DB->count_records(webhook_handler::LOG_TABLE, ['txnid' => $txnid]));
        $this->assertSame(1, $DB->count_records(webhook_handler::LOG_TABLE, ['txnid' => (int)$othertxn->id]));
    }

    /**
     * Deleting a list of users removes only those users.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        [$course, $instance, $user] = $this->create_transaction();
        $other = $this->getDataGenerator()->create_user();
        $othertxn = transaction::create($instance, $other, instance_settings::from_instance($instance));

        $txnid = (int)$DB->get_field(transaction::TABLE, 'id', ['userid' => $user->id]);
        $this->create_webhook_log($txnid);
        $this->create_webhook_log((int)$othertxn->id);

        provider::delete_data_for_users(
            new approved_userlist(
                \context_course::instance($course->id),
                'enrol_mercadopagocpro',
                [(int)$other->id]
            )
        );

        $this->assertSame(1, $DB->count_records(transaction::TABLE, ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records(transaction::TABLE, ['userid' => $other->id]));

        $this->assertSame(1, $DB->count_records(webhook_handler::LOG_TABLE, ['txnid' => $txnid]));
        $this->assertSame(0, $DB->count_records(webhook_handler::LOG_TABLE, ['txnid' => (int)$othertxn->id]));
    }
}
