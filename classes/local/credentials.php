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

use core\encryption;

/**
 * Resolution and storage of the Mercado Pago credentials used for a request.
 *
 * Resolution order, highest priority first:
 *
 *   1. Per enrolment instance credentials (enrol_mercadopagocpro_cred), used for
 *      multi-tenant sites and for marketplace sellers connected through OAuth.
 *   2. Server level configuration: `$CFG->enrol_mercadopagocpro` or the
 *      MERCADOPAGOCPRO_* environment variables. Nothing is written to the
 *      database in this mode.
 *   3. The site wide admin settings of the plugin.
 *
 * Secrets are never returned by __toString(), never logged, and per instance
 * secrets are encrypted at rest with \core\encryption.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credentials
{

    /**
     * @var string Production environment. 
     */
    public const ENV_PRODUCTION = 'production';

    /**
     * @var string Test environment (test credentials + sandbox_init_point). 
     */
    public const ENV_TEST = 'test';

    /**
     * @var string The table holding per instance credentials. 
     */
    public const TABLE = 'enrol_mercadopagocpro_cred';

    /**
     * Constructor.
     *
     * @param string      $accesstoken   Mercado Pago access token (Bearer token).
     * @param string      $publickey     Mercado Pago public key.
     * @param string      $webhooksecret Secret signature used to validate webhook x-signature headers.
     * @param string      $environment   self::ENV_PRODUCTION or self::ENV_TEST.
     * @param string      $source        Where the credentials came from ('instance', 'server', 'site').
     * @param string|null $sellerid      Mercado Pago collector id when known (marketplace mode).
     */
    private function __construct(
        /**
         * @var string 
         */
        private string $accesstoken,
        /**
         * @var string 
         */
        private string $publickey,
        /**
         * @var string 
         */
        private string $webhooksecret,
        /**
         * @var string 
         */
        private string $environment,
        /**
         * @var string 
         */
        private string $source,
        /**
         * @var string|null 
         */
        private ?string $sellerid = null,
    ) {
    }

    /**
     * Access token used as Bearer token against api.mercadopago.com.
     *
     * @return string
     */
    public function get_access_token(): string
    {
        return $this->accesstoken;
    }

    /**
     * Public key. Only ever used server side by this plugin; Checkout Pro redirects
     * the buyer, so no key is published in the page.
     *
     * @return string
     */
    public function get_public_key(): string
    {
        return $this->publickey;
    }

    /**
     * Secret signature configured in "Your integrations" and used to validate webhooks.
     *
     * @return string
     */
    public function get_webhook_secret(): string
    {
        return $this->webhooksecret;
    }

    /**
     * Selected environment.
     *
     * @return string
     */
    public function get_environment(): string
    {
        return $this->environment;
    }

    /**
     * Whether this is a production (live) configuration.
     *
     * @return bool
     */
    public function is_live(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    /**
     * Where the credentials were resolved from, for diagnostics.
     *
     * @return string
     */
    public function get_source(): string
    {
        return $this->source;
    }

    /**
     * Collector / seller id when the credentials belong to a marketplace seller.
     *
     * @return string|null
     */
    public function get_seller_id(): ?string
    {
        return $this->sellerid;
    }

    /**
     * True when there is at least an access token to talk to the API with.
     *
     * @return bool
     */
    public function is_usable(): bool
    {
        return $this->accesstoken !== '';
    }

    /**
     * True when webhook signature validation can be performed.
     *
     * @return bool
     */
    public function can_validate_signature(): bool
    {
        return $this->webhooksecret !== '';
    }

    // __debugInfo() is a genuine PHP magic method; the moodle-cs list of them is
    // incomplete, so the naming sniff has to be silenced around this declaration.
    // The disable has to sit before the docblock, otherwise it separates the
    // docblock from the function and the MissingDocblock sniff fires instead.
    // phpcs:disable moodle.NamingConventions.ValidFunctionName.MagicLikeMethod
    /**
     * Never expose secrets through string conversion or debugging output.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'environment' => $this->environment,
            'source' => $this->source,
            'sellerid' => $this->sellerid,
            'accesstoken' => $this->accesstoken === '' ? '(empty)' : '(redacted)',
            'publickey' => $this->publickey === '' ? '(empty)' : '(redacted)',
            'webhooksecret' => $this->webhooksecret === '' ? '(empty)' : '(redacted)',
        ];
    }
    // phpcs:enable moodle.NamingConventions.ValidFunctionName.MagicLikeMethod

    /**
     * Resolve the credentials to use for an enrolment instance.
     *
     * @param  \stdClass|null $instance enrol instance record, or null for site level operations.
     * @return self
     */
    public static function resolve(?\stdClass $instance = null): self
    {
        $environment = self::get_environment_setting();

        if ($instance !== null && !empty($instance->id) && self::instance_override_allowed()) {
            $override = self::load_instance_record((int)$instance->id);
            if ($override !== null && $override['accesstoken'] !== '') {
                return new self(
                    $override['accesstoken'],
                    $override['publickey'],
                    $override['webhooksecret'],
                    $environment,
                    'instance',
                    $override['sellerid'],
                );
            }
        }

        $server = self::from_server_configuration($environment);
        if ($server !== null) {
            return $server;
        }

        return self::from_site_settings($environment);
    }

    /**
     * Configured environment for the whole site.
     *
     * @return string
     */
    public static function get_environment_setting(): string
    {
        $value = (string)get_config('enrol_mercadopagocpro', 'environment');
        return $value === self::ENV_TEST ? self::ENV_TEST : self::ENV_PRODUCTION;
    }

    /**
     * Whether courses are allowed to supply their own credentials.
     *
     * @return bool
     */
    public static function instance_override_allowed(): bool
    {
        return (bool)get_config('enrol_mercadopagocpro', 'allowinstancecredentials');
    }

    /**
     * Read credentials from config.php or the process environment.
     *
     * Recognised sources, in order:
     *   $CFG->enrol_mercadopagocpro = ['accesstoken' => ..., 'publickey' => ..., 'webhooksecret' => ...];
     *   MERCADOPAGOCPRO_ACCESS_TOKEN / MERCADOPAGOCPRO_PUBLIC_KEY / MERCADOPAGOCPRO_WEBHOOK_SECRET
     *
     * @param  string $environment
     * @return self|null null when nothing is configured at this level.
     */
    private static function from_server_configuration(string $environment): ?self
    {
        global $CFG;

        $values = [];
        if (isset($CFG->enrol_mercadopagocpro) && is_array($CFG->enrol_mercadopagocpro)) {
            $values = $CFG->enrol_mercadopagocpro;
        }

        $map = [
            'accesstoken' => 'MERCADOPAGOCPRO_ACCESS_TOKEN',
            'publickey' => 'MERCADOPAGOCPRO_PUBLIC_KEY',
            'webhooksecret' => 'MERCADOPAGOCPRO_WEBHOOK_SECRET',
        ];
        foreach ($map as $key => $envname) {
            if (empty($values[$key])) {
                $fromenv = getenv($envname);
                if ($fromenv !== false && $fromenv !== '') {
                    $values[$key] = $fromenv;
                }
            }
        }

        if (empty($values['accesstoken'])) {
            return null;
        }

        return new self(
            trim((string)$values['accesstoken']),
            trim((string)($values['publickey'] ?? '')),
            trim((string)($values['webhooksecret'] ?? '')),
            $environment,
            'server',
        );
    }

    /**
     * Read credentials from the plugin admin settings.
     *
     * @param  string $environment
     * @return self
     */
    private static function from_site_settings(string $environment): self
    {
        $prefix = $environment === self::ENV_TEST ? 'test' : '';
        return new self(
            trim((string)get_config('enrol_mercadopagocpro', $prefix . 'accesstoken')),
            trim((string)get_config('enrol_mercadopagocpro', $prefix . 'publickey')),
            trim((string)get_config('enrol_mercadopagocpro', $prefix . 'webhooksecret')),
            $environment,
            'site',
        );
    }

    /**
     * Load and decrypt the per instance credential record.
     *
     * @param  int $enrolid
     * @return array{accesstoken:string,publickey:string,webhooksecret:string,sellerid:?string}|null
     */
    private static function load_instance_record(int $enrolid): ?array
    {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['enrolid' => $enrolid]);
        if (!$record) {
            return null;
        }

        return [
            'accesstoken' => self::decrypt($record->accesstoken),
            'publickey' => self::decrypt($record->publickey),
            'webhooksecret' => self::decrypt($record->webhooksecret),
            'sellerid' => $record->sellerid !== '' ? $record->sellerid : null,
        ];
    }

    /**
     * Store (or clear) per instance credentials, encrypting every secret at rest.
     *
     * Passing null for a value leaves the stored value untouched; passing an empty
     * string clears it.
     *
     * @param  int         $enrolid
     * @param  string|null $accesstoken
     * @param  string|null $publickey
     * @param  string|null $webhooksecret
     * @param  string|null $sellerid
     * @param  string|null $refreshtoken
     * @param  int|null    $tokenexpires
     * @return void
     */
    public static function store_for_instance(
        int $enrolid,
        ?string $accesstoken = null,
        ?string $publickey = null,
        ?string $webhooksecret = null,
        ?string $sellerid = null,
        ?string $refreshtoken = null,
        ?int $tokenexpires = null,
    ): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record(self::TABLE, ['enrolid' => $enrolid]);

        $record = (object)[
            'enrolid' => $enrolid,
            'accesstoken' => $existing->accesstoken ?? null,
            'publickey' => $existing->publickey ?? null,
            'webhooksecret' => $existing->webhooksecret ?? null,
            'refreshtoken' => $existing->refreshtoken ?? null,
            'sellerid' => $existing->sellerid ?? null,
            'tokenexpires' => $existing->tokenexpires ?? null,
            'timecreated' => $existing->timecreated ?? $now,
            'timemodified' => $now,
        ];

        if ($accesstoken !== null) {
            $record->accesstoken = $accesstoken === '' ? null : self::encrypt($accesstoken);
        }
        if ($publickey !== null) {
            $record->publickey = $publickey === '' ? null : self::encrypt($publickey);
        }
        if ($webhooksecret !== null) {
            $record->webhooksecret = $webhooksecret === '' ? null : self::encrypt($webhooksecret);
        }
        if ($refreshtoken !== null) {
            $record->refreshtoken = $refreshtoken === '' ? null : self::encrypt($refreshtoken);
        }
        if ($sellerid !== null) {
            $record->sellerid = $sellerid === '' ? null : $sellerid;
        }
        if ($tokenexpires !== null) {
            $record->tokenexpires = $tokenexpires;
        }

        $isempty = empty($record->accesstoken) && empty($record->publickey)
            && empty($record->webhooksecret) && empty($record->refreshtoken);

        if ($existing) {
            if ($isempty) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
                return;
            }
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            return;
        }

        if ($isempty) {
            return;
        }
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete the credentials attached to an enrolment instance.
     *
     * @param  int $enrolid
     * @return void
     */
    public static function delete_for_instance(int $enrolid): void
    {
        global $DB;
        $DB->delete_records(self::TABLE, ['enrolid' => $enrolid]);
    }

    /**
     * Whether an instance currently has its own credentials stored.
     *
     * @param  int $enrolid
     * @return bool
     */
    public static function instance_has_credentials(int $enrolid): bool
    {
        global $DB;
        return $DB->record_exists(self::TABLE, ['enrolid' => $enrolid]);
    }

    /**
     * Encrypt a secret for storage.
     *
     * @param  string $value
     * @return string
     * @throws \moodle_exception when the site has no encryption key available.
     */
    public static function encrypt(string $value): string
    {
        if (!encryption::key_exists()) {
            // The create_key() call is safe to repeat; it does nothing if the key exists.
            encryption::create_key();
        }
        return encryption::encrypt($value);
    }

    /**
     * Decrypt a stored secret, returning an empty string when it cannot be read.
     *
     * @param  string|null $value
     * @return string
     */
    public static function decrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return encryption::decrypt($value);
        } catch (\Throwable $e) {
            util::log_error('Unable to decrypt stored Mercado Pago credential: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Build a credentials object explicitly. Used by unit tests and by the OAuth flow.
     *
     * @param  string      $accesstoken
     * @param  string      $publickey
     * @param  string      $webhooksecret
     * @param  string      $environment
     * @param  string|null $sellerid
     * @return self
     */
    public static function create(
        string $accesstoken,
        string $publickey = '',
        string $webhooksecret = '',
        string $environment = self::ENV_PRODUCTION,
        ?string $sellerid = null,
    ): self {
        return new self($accesstoken, $publickey, $webhooksecret, $environment, 'explicit', $sellerid);
    }
}
