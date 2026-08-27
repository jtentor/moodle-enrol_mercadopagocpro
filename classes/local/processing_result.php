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

namespace enrol_mercadopagocpro\local;

/**
 * Outcome of processing one payment notification or reconciliation attempt.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class processing_result
{
    /**
     * @var string The payment was looked up and the enrolment state is up to date.
     */
    public const HANDLED = 'handled';
    /**
     * @var string Nothing to do; this notification is not ours or is not actionable.
     */
    public const IGNORED = 'ignored';
    /**
     * @var string A transient failure; the caller should try again later.
     */
    public const RETRY = 'retry';

    /**
     * Constructor.
     *
     * @param string      $outcome        one of the class constants
     * @param string      $message        human readable explanation
     * @param string|null $enrolmentstate resulting enrolment state when known
     * @param string|null $paymentstatus  resulting Mercado Pago status when known
     * @param bool        $retryable      whether a retry could plausibly succeed
     */
    private function __construct(
        /**
         * @var string
         */
        public readonly string $outcome,
        /**
         * @var string
         */
        public readonly string $message = '',
        /**
         * @var string|null
         */
        public readonly ?string $enrolmentstate = null,
        /**
         * @var string|null
         */
        public readonly ?string $paymentstatus = null,
        /**
         * @var bool
         */
        public readonly bool $retryable = false,
    ) {
    }

    /**
     * The notification produced (or confirmed) an enrolment decision.
     *
     * @param  string $enrolmentstate
     * @param  string $paymentstatus
     * @return self
     */
    public static function handled(string $enrolmentstate, string $paymentstatus): self {
        return new self(self::HANDLED, 'Processed.', $enrolmentstate, $paymentstatus);
    }

    /**
     * Nothing to do.
     *
     * @param  string $message
     * @return self
     */
    public static function ignored(string $message): self {
        return new self(self::IGNORED, $message);
    }

    /**
     * Transient failure.
     *
     * @param  string $message
     * @param  bool   $retryable
     * @return self
     */
    public static function retry(string $message, bool $retryable = true): self {
        return new self(self::RETRY, $message, null, null, $retryable);
    }

    /**
     * Whether an enrolment decision was reached.
     *
     * @return bool
     */
    public function is_handled(): bool {
        return $this->outcome === self::HANDLED;
    }

    /**
     * Whether the caller should schedule another attempt.
     *
     * @return bool
     */
    public function should_retry(): bool {
        return $this->outcome === self::RETRY && $this->retryable;
    }
}
