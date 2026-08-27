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

namespace enrol_mercadopagocpro\event;

/**
 * A Mercado Pago webhook notification was rejected because its signature could
 * not be verified.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_rejected extends \core\event\base
{
    /**
     * Initialise the event data.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:webhook_rejected', 'enrol_mercadopagocpro');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description() {
        return "A Mercado Pago notification for resource '{$this->other['dataid']}' was rejected: "
            . "signature {$this->other['reason']}.";
    }

    /**
     * Build the event from a normalised notification.
     *
     * @param  array  $notification
     * @param  string $reason       signature status
     * @return self
     */
    public static function create_from_notification(array $notification, string $reason): self {
        return self::create(
            [
            'context' => \context_system::instance(),
            'other' => [
                'type' => (string)$notification['type'],
                'dataid' => (string)$notification['dataid'],
                'requestid' => (string)$notification['requestid'],
                'reason' => $reason,
            ],
            ]
        );
    }
}
