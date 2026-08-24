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

use core\message\message;

/**
 * Sends the payment related notifications to buyers and to course staff.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_notifier {

    /** @var string Payment approved and enrolment active. */
    public const EVENT_APPROVED = 'approved';
    /** @var string Payment pending / in process. */
    public const EVENT_PENDING = 'pending';
    /** @var string Payment rejected or cancelled before approval. */
    public const EVENT_FAILED = 'failed';
    /** @var string Payment refunded or charged back. */
    public const EVENT_REVERSED = 'reversed';

    /** @var array<string,string> Event to message provider mapping. */
    private const PROVIDERS = [
        self::EVENT_APPROVED => 'payment_approved',
        self::EVENT_PENDING => 'payment_pending',
        self::EVENT_FAILED => 'payment_failed',
        self::EVENT_REVERSED => 'payment_reversed',
    ];

    /** @var string[] Events that also notify course staff. */
    private const STAFF_EVENTS = [self::EVENT_APPROVED, self::EVENT_REVERSED];

    /**
     * Send the notifications for one event.
     *
     * @param string $event one of the EVENT_* constants
     * @param \stdClass $instance enrol instance
     * @param \stdClass $transaction transaction record
     * @return void
     */
    public function send(string $event, \stdClass $instance, \stdClass $transaction): void {
        global $DB;

        if (!isset(self::PROVIDERS[$event])) {
            return;
        }

        $user = \core_user::get_user((int)$transaction->userid);
        $course = $DB->get_record('course', ['id' => $instance->courseid]);
        if (!$user || !$course || !empty($user->deleted)) {
            return;
        }

        $context = \context_course::instance($course->id);
        $a = (object)[
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'amount' => $this->format_amount($transaction),
            'paymentid' => (string)($transaction->paymentid ?? ''),
            'status' => status::label($transaction->status),
            'statusdetail' => (string)($transaction->statusdetail ?? ''),
            'fullname' => fullname($user),
            'date' => userdate($transaction->timemodified),
        ];

        $this->send_to_user($event, $user, $course, $a);

        if (in_array($event, self::STAFF_EVENTS, true)) {
            $this->send_to_staff($event, $context, $course, $user, $a);
        }
    }

    /**
     * Message the buyer.
     *
     * @param string $event
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $a placeholders
     * @return void
     */
    protected function send_to_user(string $event, \stdClass $user, \stdClass $course, \stdClass $a): void {
        $provider = self::PROVIDERS[$event];

        $message = new message();
        $message->component = 'enrol_mpcheckoutpro';
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('message_' . $provider . '_subject', 'enrol_mpcheckoutpro', $a);
        $message->fullmessage = get_string('message_' . $provider . '_body', 'enrol_mpcheckoutpro', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $a->courseurl;
        $message->contexturlname = $a->coursename;
        $message->courseid = $course->id;

        message_send($message);
    }

    /**
     * Message the course staff who can see payment transactions.
     *
     * @param string $event
     * @param \context_course $context
     * @param \stdClass $course
     * @param \stdClass $buyer
     * @param \stdClass $a
     * @return void
     */
    protected function send_to_staff(
        string $event,
        \context_course $context,
        \stdClass $course,
        \stdClass $buyer,
        \stdClass $a,
    ): void {
        $recipients = get_users_by_capability($context, 'enrol/mpcheckoutpro:viewtransactions', 'u.*');
        if (!$recipients) {
            return;
        }

        $stringkey = $event === self::EVENT_APPROVED ? 'staffapproved' : 'staffreversed';
        $subject = get_string('message_' . $stringkey . '_subject', 'enrol_mpcheckoutpro', $a);
        $body = get_string('message_' . $stringkey . '_body', 'enrol_mpcheckoutpro', $a);
        $reporturl = util::plugin_url('transactions.php', ['courseid' => $course->id])->out(false);

        foreach ($recipients as $recipient) {
            if ((int)$recipient->id === (int)$buyer->id || !empty($recipient->deleted)) {
                continue;
            }
            $message = new message();
            $message->component = 'enrol_mpcheckoutpro';
            $message->name = 'teacher_notification';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($body, false, false, true);
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $reporturl;
            $message->contexturlname = get_string('transactions', 'enrol_mpcheckoutpro');
            $message->courseid = $course->id;

            message_send($message);
        }
    }

    /**
     * Format the transaction amount for display in a message.
     *
     * @param \stdClass $transaction
     * @return string
     */
    protected function format_amount(\stdClass $transaction): string {
        $amount = (float)$transaction->amount;
        $currency = (string)$transaction->currency;
        $locale = get_string('localecldr', 'langconfig');
        if (class_exists('\NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        return $currency . ' ' . number_format($amount, 2);
    }
}
