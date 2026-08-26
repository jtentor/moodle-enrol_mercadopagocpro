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

namespace enrol_mercadopagocpro\task;

use enrol_mercadopagocpro\local\payment_processor;
use enrol_mercadopagocpro\local\transaction;
use enrol_mercadopagocpro\local\util;

/**
 * Re-queries Mercado Pago for every transaction that has not reached a final state.
 *
 * This is the safety net that makes a lost webhook harmless: no matter what
 * happens to the notifications, every payment converges to the right enrolment.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_payments extends \core\task\scheduled_task
{

    /**
     * @var int Maximum transactions handled in one run. 
     */
    private const BATCH_SIZE = 100;

    /**
     * Task name shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('task:reconcile_payments', 'enrol_mercadopagocpro');
    }

    /**
     * Run the reconciliation.
     *
     * @return void
     */
    public function execute()
    {
        if (!enrol_is_enabled('mercadopagocpro')) {
            mtrace('enrol_mercadopagocpro is disabled, skipping reconciliation.');
            return;
        }

        $maxattempts = (int)get_config('enrol_mercadopagocpro', 'reconcilemaxattempts') ?: 60;
        $maxage = (int)get_config('enrol_mercadopagocpro', 'reconcilemaxage') ?: 90 * DAYSECS;

        $pending = transaction::get_pending_for_reconciliation(self::BATCH_SIZE, $maxattempts, $maxage);
        if (!$pending) {
            return;
        }

        mtrace('Reconciling ' . count($pending) . ' Mercado Pago transaction(s).');
        $processor = new payment_processor();
        $handled = 0;

        foreach ($pending as $record) {
            transaction::touch_reconcile((int)$record->id);
            try {
                $result = $processor->reconcile($record);
                if ($result->is_handled()) {
                    $handled++;
                }
                mtrace('  txn ' . $record->id . ': ' . $result->outcome . ' - ' . $result->message);
            } catch (\Throwable $e) {
                util::log_error(
                    'Reconciliation failed for a transaction: ' . $e->getMessage(), [
                    'txnid' => (int)$record->id,
                    ]
                );
                mtrace('  txn ' . $record->id . ': error - ' . $e->getMessage());
            }
        }

        mtrace('Reconciliation finished, ' . $handled . ' transaction(s) settled.');
    }
}
