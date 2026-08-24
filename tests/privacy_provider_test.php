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

namespace enrol_mpcheckoutpro;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_mpcheckoutpro\local\instance_settings;
use enrol_mpcheckoutpro\local\transaction;
use enrol_mpcheckoutpro\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/mpcheckoutpro/tests/helper_trait.php');

/**
 * Tests for the privacy provider.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_mpcheckoutpro\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {

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
     * The metadata describes the table and the external transfer.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('enrol_mpcheckoutpro'));
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);
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
        $userlist = new userlist($context, 'enrol_mpcheckoutpro');
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

        provider::export_user_data(new approved_contextlist(
            $user,
            'enrol_mpcheckoutpro',
            [$context->id]
        ));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'enrol_mpcheckoutpro')]);
        $this->assertCount(1, $data->transactions);
    }

    /**
     * Deleting a context removes every transaction in it.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        [$course] = $this->create_transaction();
        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertSame(0, $DB->count_records(transaction::TABLE));
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
        transaction::create($instance, $other, instance_settings::from_instance($instance));

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'enrol_mpcheckoutpro',
            [\context_course::instance($course->id)->id]
        ));

        $this->assertSame(0, $DB->count_records(transaction::TABLE, ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records(transaction::TABLE, ['userid' => $other->id]));
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
        transaction::create($instance, $other, instance_settings::from_instance($instance));

        provider::delete_data_for_users(new approved_userlist(
            \context_course::instance($course->id),
            'enrol_mpcheckoutpro',
            [(int)$other->id]
        ));

        $this->assertSame(1, $DB->count_records(transaction::TABLE, ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records(transaction::TABLE, ['userid' => $other->id]));
    }
}
