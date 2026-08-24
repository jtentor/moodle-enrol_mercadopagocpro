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
 * Small shared helpers: signed external references, redaction and logging.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util
{

    /**
     * @var string Prefix of every external_reference this plugin generates. 
     */
    public const REFERENCE_PREFIX = 'mpcp';

    /**
     * @var string[] Keys that must never be written to the database or the log. 
     */
    private const REDACT_KEYS = [
        'access_token', 'refresh_token', 'client_secret', 'public_key', 'authorization',
        'card', 'token', 'security_code', 'cvv', 'password', 'secret',
        'first_six_digits', 'last_four_digits', 'identification', 'email', 'phone',
        'response',
    ];

    /**
     * Build the signed external reference sent to Mercado Pago.
     *
     * The HMAC lets return.php and the webhook trust that a reference actually came
     * from this site before any database lookup takes place.
     *
     * @param  int $txnid   local transaction id
     * @param  int $enrolid enrolment instance id
     * @param  int $userid  buyer id
     * @return string
     */
    public static function build_external_reference(int $txnid, int $enrolid, int $userid): string
    {
        $body = sprintf('%s-%d-%d-%d', self::REFERENCE_PREFIX, $enrolid, $userid, $txnid);
        return $body . '-' . substr(self::sign($body), 0, 16);
    }

    /**
     * Parse and verify an external reference produced by this plugin.
     *
     * @param  string|null $reference
     * @return array{enrolid:int,userid:int,txnid:int}|null null when it is not ours or the signature fails.
     */
    public static function parse_external_reference(?string $reference): ?array
    {
        if ($reference === null || $reference === '') {
            return null;
        }
        $parts = explode('-', $reference);
        if (count($parts) !== 5 || $parts[0] !== self::REFERENCE_PREFIX) {
            return null;
        }
        [, $enrolid, $userid, $txnid, $signature] = $parts;
        if (!ctype_digit($enrolid) || !ctype_digit($userid) || !ctype_digit($txnid)) {
            return null;
        }
        $body = sprintf('%s-%d-%d-%d', self::REFERENCE_PREFIX, (int)$enrolid, (int)$userid, (int)$txnid);
        $expected = substr(self::sign($body), 0, 16);
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        return [
            'enrolid' => (int)$enrolid,
            'userid' => (int)$userid,
            'txnid' => (int)$txnid,
        ];
    }

    /**
     * HMAC of a value with a plugin owned secret derived from the site's salt.
     *
     * @param  string $value
     * @return string hex digest
     */
    private static function sign(string $value): string
    {
        return hash_hmac('sha256', $value, self::get_reference_secret());
    }

    /**
     * Lazily created secret used only to sign external references.
     *
     * @return string
     */
    private static function get_reference_secret(): string
    {
        $secret = get_config('enrol_mpcheckoutpro', 'referencesecret');
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
            set_config('referencesecret', $secret, 'enrol_mpcheckoutpro');
        }
        return (string)$secret;
    }

    /**
     * Recursively remove sensitive values from an array before it is stored or logged.
     *
     * @param  mixed $data
     * @param  int   $depth internal guard against pathological structures
     * @return mixed
     */
    public static function redact($data, int $depth = 0)
    {
        if ($depth > 12) {
            return '(truncated)';
        }
        if (is_object($data)) {
            $data = (array)$data;
        }
        if (!is_array($data)) {
            return $data;
        }
        $out = [];
        foreach ($data as $key => $value) {
            $lowerkey = is_string($key) ? strtolower($key) : $key;
            if (is_string($lowerkey) && in_array($lowerkey, self::REDACT_KEYS, true)) {
                $out[$key] = '(redacted)';
                continue;
            }
            $out[$key] = self::redact($value, $depth + 1);
        }
        return $out;
    }

    /**
     * JSON encode a payload for storage, redacted and length capped.
     *
     * @param  mixed $data
     * @param  int   $maxlength
     * @return string
     */
    public static function encode_for_storage($data, int $maxlength = 60000): string
    {
        $json = json_encode(self::redact($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '{"error":"json_encode failed"}';
        }
        if (strlen($json) > $maxlength) {
            return substr($json, 0, $maxlength - 16) . '..."(truncated)"}';
        }
        return $json;
    }

    /**
     * Write a debug line to the Moodle error log when plugin logging is enabled.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function log_debug(string $message, array $context = []): void
    {
        if (!get_config('enrol_mpcheckoutpro', 'debuglogging')) {
            return;
        }
        self::write_log('DEBUG', $message, $context);
    }

    /**
     * Write an error line to the Moodle error log. Always emitted.
     *
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    public static function log_error(string $message, array $context = []): void
    {
        self::write_log('ERROR', $message, $context);
    }

    /**
     * Emit one log line.
     *
     * @param  string $level
     * @param  string $message
     * @param  array  $context
     * @return void
     */
    private static function write_log(string $level, string $message, array $context): void
    {
        $line = '[enrol_mpcheckoutpro][' . $level . '] ' . $message;
        if ($context) {
            $line .= ' ' . self::encode_for_storage($context, 4000);
        }
        // debugging() honours the site's debug settings; error_log guarantees the
        // line reaches the server log even on a production site.
        error_log($line);
    }

    /**
     * Absolute https URL of one of the plugin end points.
     *
     * @param  string $script file name inside the plugin directory
     * @param  array  $params query parameters
     * @return \moodle_url
     */
    public static function plugin_url(string $script, array $params = []): \moodle_url
    {
        return new \moodle_url('/enrol/mpcheckoutpro/' . $script, $params);
    }

    /**
     * Whether the site is reachable over https, which Mercado Pago requires for
     * notification_url and back_urls.
     *
     * @return bool
     */
    public static function site_is_https(): bool
    {
        global $CFG;
        return strpos((string)$CFG->wwwroot, 'https://') === 0;
    }

    /**
     * Format an amount the way Mercado Pago expects it in a preference item.
     *
     * @param  float $amount
     * @return float
     */
    public static function normalise_amount(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Currencies supported by the Mercado Pago sites where Checkout Pro operates.
     *
     * @return string[] ISO-4217 codes
     */
    public static function supported_currencies(): array
    {
        return ['ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU'];
    }
}
