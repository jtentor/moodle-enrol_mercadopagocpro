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
 * Fixed window rate limiter backed by the Moodle cache API.
 *
 * Used on the two endpoints that can be reached without a Moodle session
 * (webhook.php) or that cost an outbound API call (checkout.php).
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter
{
    /**
     * Constructor.
     *
     * @param string $bucket logical bucket name, e.g. 'webhook'
     * @param int    $limit  maximum number of hits allowed per window
     * @param int    $window window length in seconds
     */
    public function __construct(
        /**
         * @var string
         */
        protected string $bucket,
        /**
         * @var int
         */
        protected int $limit,
        /**
         * @var int
         */
        protected int $window = 60,
    ) {
    }

    /**
     * Register one hit and report whether the caller is within the limit.
     *
     * Fails open: if the cache is unavailable the request is allowed through,
     * because dropping a Mercado Pago notification is worse than serving it.
     *
     * @param  string $key caller identity, typically the remote IP
     * @return bool true when the request may proceed
     */
    public function allow(string $key): bool {
        if ($this->limit <= 0) {
            return true;
        }

        try {
            $cache = \cache::make('enrol_mpcheckoutpro', 'ratelimit');
            $window = (int)floor(time() / max(1, $this->window));
            $cachekey = $this->bucket . '_' . $window . '_' . sha1($key);

            $count = (int)$cache->get($cachekey);
            $count++;
            $cache->set($cachekey, $count);

            return $count <= $this->limit;
        } catch (\Throwable $e) {
            util::log_error('Rate limiter unavailable, allowing request: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Build the limiter configured for the public webhook endpoint.
     *
     * @return self
     */
    public static function for_webhook(): self {
        $limit = (int)get_config('enrol_mpcheckoutpro', 'webhookratelimit');
        return new self('webhook', $limit > 0 ? $limit : 120, 60);
    }

    /**
     * Build the limiter configured for preference creation.
     *
     * @return self
     */
    public static function for_checkout(): self {
        $limit = (int)get_config('enrol_mpcheckoutpro', 'checkoutratelimit');
        return new self('checkout', $limit > 0 ? $limit : 10, 60);
    }

    /**
     * Best effort client identity for rate limiting.
     *
     * @return string
     */
    public static function client_key(): string {
        $ip = getremoteaddr();
        return $ip !== null && $ip !== '' ? $ip : 'unknown';
    }
}
