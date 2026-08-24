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
 * Raised when a call to the Mercado Pago API fails.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {

    /** @var int HTTP status returned by the API, 0 for transport level failures. */
    protected int $statuscode;

    /** @var string The operation that failed. */
    protected string $operation;

    /**
     * Constructor.
     *
     * @param string $message raw message from the API or the transport
     * @param int $statuscode HTTP status, 0 when the request never completed
     * @param string $operation human readable operation name
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, int $statuscode, string $operation, ?\Throwable $previous = null) {
        $this->statuscode = $statuscode;
        $this->operation = $operation;
        $a = (object)[
            'operation' => $operation,
            'status' => $statuscode,
        ];
        parent::__construct('error:apicall', 'enrol_mpcheckoutpro', '', $a, $message);
        if ($previous !== null) {
            // moodle_exception does not accept a previous exception, keep it for the log.
            $this->debuginfo = $message . ' (' . get_class($previous) . ')';
        }
    }

    /**
     * HTTP status returned by Mercado Pago, 0 when the request never reached the API.
     *
     * @return int
     */
    public function get_status_code(): int {
        return $this->statuscode;
    }

    /**
     * The failed operation.
     *
     * @return string
     */
    public function get_operation(): string {
        return $this->operation;
    }

    /**
     * Whether retrying the same call later could plausibly succeed.
     *
     * @return bool
     */
    public function is_retryable(): bool {
        return $this->statuscode === 0
            || $this->statuscode === 408
            || $this->statuscode === 429
            || $this->statuscode >= 500;
    }
}
