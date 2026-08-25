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

namespace enrol_mpcheckoutpro\local;

/**
 * Applies enrolment decisions to Moodle.
 *
 * All public methods here are expected to run while the caller holds the
 * transaction lock obtained through {@see self::get_lock()}.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://moodledev.io/docs/5.2/apis/core/lock
 */
class enrolment_manager
{

    /**
     * @var string Lock factory namespace. 
     */
    public const LOCK_TYPE = 'enrol_mpcheckoutpro_transaction';

    /**
     * Acquire the per transaction lock.
     *
     * The webhook, the return page and the reconciliation task can all try to
     * settle the same payment at the same instant; the lock makes the enrolment
     * decision single threaded per transaction.
     *
     * @param  int $txnid
     * @param  int $timeout seconds to wait
     * @return \core\lock\lock|null null when the lock could not be obtained
     */
    public static function get_lock(int $txnid, int $timeout = 10): ?\core\lock\lock
    {
        $factory = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE);
        $lock = $factory->get_lock('txn_' . $txnid, $timeout);
        return $lock === false ? null : $lock;
    }

    /**
     * Enrol (or re-activate) the buyer.
     *
     * @param  \stdClass         $instance    enrol instance
     * @param  \stdClass         $transaction local transaction
     * @param  instance_settings $settings
     * @return void
     */
    public static function activate(\stdClass $instance, \stdClass $transaction, instance_settings $settings): void
    {
        global $DB;

        $plugin = enrol_get_plugin('mpcheckoutpro');
        if (!$plugin) {
            throw new \coding_exception('The mpcheckoutpro enrolment plugin could not be loaded.');
        }

        $userid = (int)$transaction->userid;
        [$timestart, $timeend] = self::calculate_period($instance, $settings, $userid);

        $existing = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if ($existing) {
            // Re-activating a holding enrolment: keep the original start date.
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, $existing->timestart, $timeend);
            // enrol_user is still called so the role assignment is (re)created.
            $plugin->enrol_user(
                $instance, $userid, $settings->roleid ?: null,
                $existing->timestart, $timeend, ENROL_USER_ACTIVE
            );
        } else {
            $plugin->enrol_user(
                $instance, $userid, $settings->roleid ?: null,
                $timestart, $timeend, ENROL_USER_ACTIVE
            );
        }

        self::assign_group($instance, $userid, $settings);
    }

    /**
     * Create a suspended holding enrolment while an offline payment settles.
     *
     * The user appears in the course as suspended: they cannot access it, but the
     * teacher can see the pending payment and the enrolment is instantly activated
     * when the payment is approved.
     *
     * @param  \stdClass         $instance
     * @param  \stdClass         $transaction
     * @param  instance_settings $settings
     * @return void
     */
    public static function hold(\stdClass $instance, \stdClass $transaction, instance_settings $settings): void
    {
        global $DB;

        $plugin = enrol_get_plugin('mpcheckoutpro');
        if (!$plugin) {
            throw new \coding_exception('The mpcheckoutpro enrolment plugin could not be loaded.');
        }

        $userid = (int)$transaction->userid;
        if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            // Never downgrade an existing enrolment because of a second pending payment.
            return;
        }

        [$timestart, $timeend] = self::calculate_period($instance, $settings, $userid);
        $plugin->enrol_user(
            $instance, $userid, $settings->roleid ?: null,
            $timestart, $timeend, ENROL_USER_SUSPENDED
        );
    }

    /**
     * Revoke access after a refund, chargeback or cancellation.
     *
     * @param  \stdClass         $instance
     * @param  \stdClass         $transaction
     * @param  instance_settings $settings
     * @return string the resulting enrolment state
     */
    public static function revoke(\stdClass $instance, \stdClass $transaction, instance_settings $settings): string
    {
        global $DB;

        $plugin = enrol_get_plugin('mpcheckoutpro');
        if (!$plugin) {
            throw new \coding_exception('The mpcheckoutpro enrolment plugin could not be loaded.');
        }

        $userid = (int)$transaction->userid;
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            return status::ENROLMENT_NONE;
        }

        // Never revoke access that a different, still valid payment is paying for.
        if (self::has_other_granting_transaction($transaction)) {
            util::log_debug(
                'Reversal ignored: another approved transaction still grants access', [
                'txnid' => (int)$transaction->id,
                'userid' => $userid,
                ]
            );
            return (string)$transaction->enrolmentstate;
        }

        switch ($settings->reversalaction) {
        case instance_settings::REVERSAL_UNENROL:
            $plugin->unenrol_user($instance, $userid);
            return status::ENROLMENT_UNENROLLED;

        case instance_settings::REVERSAL_SUSPEND:
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            self::remove_roles($instance, $userid);
            return status::ENROLMENT_SUSPENDED;

        case instance_settings::REVERSAL_KEEP:
        default:
            return (string)$transaction->enrolmentstate;
        }
    }

    /**
     * Whether another transaction of the same user on the same instance is approved
     * and still granting access.
     *
     * @param  \stdClass $transaction
     * @return bool
     */
    protected static function has_other_granting_transaction(\stdClass $transaction): bool
    {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(status::granting(), SQL_PARAMS_NAMED, 'gs');
        $params['enrolid'] = $transaction->enrolid;
        $params['userid'] = $transaction->userid;
        $params['id'] = $transaction->id;

        return $DB->record_exists_select(
            transaction::TABLE,
            "enrolid = :enrolid AND userid = :userid AND id <> :id AND status $insql",
            $params
        );
    }

    /**
     * Remove the role assignments this plugin created for a user.
     *
     * This mirrors what enrol_plugin::process_expirations() does for
     * ENROL_EXT_REMOVED_SUSPENDNOROLES: because roles_protected() is false the
     * role assignments carry no component, so the safe roles to remove have to
     * be worked out rather than looked up.
     *
     * @param  \stdClass $instance
     * @param  int       $userid
     * @return void
     */
    protected static function remove_roles(\stdClass $instance, int $userid): void
    {
        global $DB;

        $context = \context_course::instance($instance->courseid);

        $count = $DB->count_records('role_assignments', ['userid' => $userid, 'contextid' => $context->id]);
        if ($count == 1) {
            role_unassign_all(['userid' => $userid, 'contextid' => $context->id, 'component' => '', 'itemid' => 0]);
        } else if ($count > 1 && $instance->roleid) {
            role_unassign((int)$instance->roleid, $userid, $context->id, '', 0);
        }

        // Remove anything explicitly owned by this instance, then clean up
        // sub-contexts when no course level role is left.
        role_unassign_all(
            [
            'userid' => $userid,
            'contextid' => $context->id,
            'component' => 'enrol_mpcheckoutpro',
            'itemid' => $instance->id,
            ], true
        );

        if (0 == $DB->count_records('role_assignments', ['userid' => $userid, 'contextid' => $context->id])) {
            role_unassign_all(['userid' => $userid, 'contextid' => $context->id, 'component' => '', 'itemid' => 0], true);
        }
    }

    /**
     * Add the user to the configured group.
     *
     * @param  \stdClass         $instance
     * @param  int               $userid
     * @param  instance_settings $settings
     * @return void
     */
    protected static function assign_group(\stdClass $instance, int $userid, instance_settings $settings): void
    {
        global $CFG, $DB;

        if ($settings->groupid <= 0) {
            return;
        }
        require_once $CFG->dirroot . '/group/lib.php';

        // The group must still exist and still belong to this course.
        $group = $DB->get_record('groups', ['id' => $settings->groupid, 'courseid' => $instance->courseid]);
        if (!$group) {
            util::log_error(
                'Configured group no longer exists, skipping group assignment', [
                'groupid' => $settings->groupid,
                'courseid' => $instance->courseid,
                ]
            );
            return;
        }

        if (groups_is_member($group->id, $userid)) {
            return;
        }
        groups_add_member($group->id, $userid);
    }

    /**
     * Work out timestart / timeend for a new enrolment.
     *
     * @param  \stdClass         $instance
     * @param  instance_settings $settings
     * @param  int               $userid
     * @return array{0:int,1:int}
     */
    protected static function calculate_period(\stdClass $instance, instance_settings $settings, int $userid): array
    {
        $timestart = time();
        // Align the start with the beginning of the day, as core enrolment plugins do.
        $timestart = ($timestart - ($timestart % 60));

        if ($settings->enrolstartdate > 0 && $settings->enrolstartdate > $timestart) {
            $timestart = $settings->enrolstartdate;
        }

        $timeend = 0;
        if ($settings->enrolperiod > 0) {
            $timeend = $timestart + $settings->enrolperiod;
        }
        if ($settings->enrolenddate > 0 && ($timeend === 0 || $timeend > $settings->enrolenddate)) {
            $timeend = $settings->enrolenddate;
        }

        unset($instance, $userid);
        return [$timestart, $timeend];
    }

    /**
     * Whether the instance still has room for one more enrolment.
     *
     * @param  \stdClass         $instance
     * @param  instance_settings $settings
     * @return bool
     */
    public static function has_capacity(\stdClass $instance, instance_settings $settings): bool
    {
        if ($settings->maxenrolled <= 0) {
            return true;
        }
        return transaction::count_active_enrolments((int)$instance->id) < $settings->maxenrolled;
    }
}
