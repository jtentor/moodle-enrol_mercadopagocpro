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

use MercadoPago\Client\OAuth\OAuthClient;
use MercadoPago\Client\OAuth\OAuthCreateRequest;
use MercadoPago\Client\OAuth\OAuthRefreshRequest;

/**
 * Split payments: connects a course to a Mercado Pago seller through OAuth.
 *
 * In the split payments (marketplace) model the preference must be created with
 * the *seller's* access token, and the marketplace commission travels in
 * marketplace_fee. This helper drives the documented OAuth flow and stores the
 * resulting seller token encrypted against the enrolment instance.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.br/developers/en/docs/checkout-pro/how-tos/integrate-marketplace
 * @see       https://www.mercadopago.com.br/developers/en/docs/security/oauth/creation
 */
class oauth_helper
{
    /**
     * @var string User preference key holding the CSRF state of an in-flight flow.
     */
    private const STATE_PREFERENCE = 'enrol_mpcheckoutpro_oauthstate';

    /**
     * Whether split payments are switched on and configured at site level.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool)get_config('enrol_mpcheckoutpro', 'marketplaceenabled')
            && self::get_client_id() !== ''
            && self::get_client_secret() !== '';
    }

    /**
     * Application (client) id of the marketplace application.
     *
     * @return string
     */
    public static function get_client_id(): string {
        return trim((string)get_config('enrol_mpcheckoutpro', 'marketplaceclientid'));
    }

    /**
     * Application client secret.
     *
     * @return string
     */
    public static function get_client_secret(): string {
        return trim((string)get_config('enrol_mpcheckoutpro', 'marketplaceclientsecret'));
    }

    /**
     * The redirect URI that must be registered in the Mercado Pago application.
     *
     * @return \moodle_url
     */
    public static function get_redirect_uri(): \moodle_url {
        return util::plugin_url('oauth.php');
    }

    /**
     * Build the authorization URL for one enrolment instance and remember the state.
     *
     * @param  int $enrolid
     * @return string
     */
    public static function build_authorization_url(int $enrolid): string {
        sdk::configure();

        $state = $enrolid . ':' . bin2hex(random_bytes(16));
        set_user_preference(self::STATE_PREFERENCE, $state);

        $client = new OAuthClient();
        return $client->getAuthorizationURL(
            self::get_client_id(),
            self::get_redirect_uri()->out(false),
            $state
        );
    }

    /**
     * Verify a returned state parameter and extract the enrolment instance id.
     *
     * @param  string $state
     * @return int the enrol instance id, 0 when the state does not match
     */
    public static function consume_state(string $state): int {
        $stored = (string)get_user_preferences(self::STATE_PREFERENCE, '');
        unset_user_preference(self::STATE_PREFERENCE);

        if ($stored === '' || !hash_equals($stored, $state)) {
            return 0;
        }
        $parts = explode(':', $state, 2);
        return ctype_digit($parts[0]) ? (int)$parts[0] : 0;
    }

    /**
     * Exchange an authorization code for the seller's credentials and store them.
     *
     * @param  int    $enrolid
     * @param  string $code
     * @return \stdClass{sellerid:string,livemode:bool}
     * @throws api_exception
     */
    public static function exchange_code(int $enrolid, string $code): \stdClass {
        sdk::configure();

        $request = new OAuthCreateRequest();
        $request->client_id = self::get_client_id();
        $request->client_secret = self::get_client_secret();
        $request->code = $code;
        $request->redirect_uri = self::get_redirect_uri()->out(false);

        try {
            $client = new OAuthClient();
            $oauth = $client->create($request);
        } catch (\Throwable $e) {
            throw new api_exception($e->getMessage(), 0, 'POST /oauth/token', $e);
        }

        self::store($enrolid, $oauth);

        return (object)[
            'sellerid' => (string)($oauth->user_id ?? ''),
            'livemode' => (bool)($oauth->live_mode ?? false),
        ];
    }

    /**
     * Refresh an expiring seller token.
     *
     * @param  int $enrolid
     * @return bool true when a new token was stored
     */
    public static function refresh(int $enrolid): bool {
        global $DB;

        $record = $DB->get_record(credentials::TABLE, ['enrolid' => $enrolid]);
        if (!$record || empty($record->refreshtoken)) {
            return false;
        }

        $refreshtoken = credentials::decrypt($record->refreshtoken);
        if ($refreshtoken === '') {
            return false;
        }

        sdk::configure();

        $request = new OAuthRefreshRequest();
        $request->client_id = self::get_client_id();
        $request->client_secret = self::get_client_secret();
        $request->refresh_token = $refreshtoken;

        try {
            $client = new OAuthClient();
            $oauth = $client->refresh($request);
        } catch (\Throwable $e) {
            util::log_error(
                'Could not refresh the Mercado Pago seller token: ' . $e->getMessage(),
                [
                'enrolid' => $enrolid,
                ]
            );
            return false;
        }

        self::store($enrolid, $oauth);
        return true;
    }

    /**
     * Persist an OAuth response.
     *
     * @param  int    $enrolid
     * @param  object $oauth
     * @return void
     */
    protected static function store(int $enrolid, object $oauth): void {
        $expiresin = (int)($oauth->expires_in ?? 0);
        credentials::store_for_instance(
            $enrolid,
            (string)($oauth->access_token ?? ''),
            (string)($oauth->public_key ?? ''),
            null,
            (string)($oauth->user_id ?? ''),
            (string)($oauth->refresh_token ?? ''),
            $expiresin > 0 ? time() + $expiresin : null,
        );
    }

    /**
     * Whether the stored seller token is close to expiring.
     *
     * @param  int $enrolid
     * @param  int $threshold seconds before expiry at which a refresh is due
     * @return bool
     */
    public static function needs_refresh(int $enrolid, int $threshold = WEEKSECS): bool {
        global $DB;
        $expires = $DB->get_field(credentials::TABLE, 'tokenexpires', ['enrolid' => $enrolid]);
        if (empty($expires)) {
            return false;
        }
        return ((int)$expires - time()) < $threshold;
    }
}
