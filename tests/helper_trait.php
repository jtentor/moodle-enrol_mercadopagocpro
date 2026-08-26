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

namespace enrol_mercadopagocpro;

use enrol_mercadopagocpro\local\sdk;
use enrol_mercadopagocpro\tests\fixtures\mock_http_client;
use MercadoPago\MercadoPagoConfig;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// The fixture below declares "implements MPHttpClient", so PHP has to resolve
// that interface while the file is being compiled. Nothing has registered the
// Mercado Pago autoloader at this point in a test run, so it must happen first.
// sdk::register() is idempotent and setup_plugin() calls it again harmlessly.
sdk::register();
if (!interface_exists(\MercadoPago\Net\MPHttpClient::class)) {
    throw new \coding_exception(
        'The bundled Mercado Pago SDK could not be loaded from ' .
        'enrol/mercadopagocpro/vendor/mercadopago/src. The test fixtures implement ' .
        'its interfaces, so the suite cannot run without it.'
    );
}
require_once $CFG->dirroot . '/enrol/mercadopagocpro/tests/fixtures/mock_http_client.php';

/**
 * Shared setup for the enrol_mercadopagocpro test suite.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait helper_trait
{

    /**
     * @var mock_http_client|null 
     */
    protected ?mock_http_client $mpclient = null;

    /**
     * @var bool Whether message redirection has already been started in this test. 
     */
    protected bool $messagesredirected = false;

    /**
     * Enable the plugin and point the SDK at the mock HTTP client.
     *
     * @return mock_http_client
     */
    protected function setup_plugin(): mock_http_client
    {
        global $CFG;

        $this->resetAfterTest();

        // Mercado Pago requires https for notification_url and back_urls.
        $CFG->wwwroot = str_replace('http://', 'https://', $CFG->wwwroot);

        // Make the suite hermetic with respect to server level credentials.
        //
        // credentials::resolve() deliberately ranks the server configuration above
        // the site settings, so a real access token present on the machine running
        // the tests would override the fake one set below. PHPUnit's bootstrap
        // already discards $CFG->enrol_mercadopagocpro, but it cannot discard the
        // process environment, which a production server normally has populated.
        // Clearing it here keeps the tests from ever seeing a live credential.
        unset($CFG->enrol_mercadopagocpro);
        foreach (
            [
            'MERCADOPAGOCPRO_ACCESS_TOKEN',
            'MERCADOPAGOCPRO_PUBLIC_KEY',
            'MERCADOPAGOCPRO_WEBHOOK_SECRET',
            ] as $envname
        ) {
            putenv($envname);
            unset($_ENV[$envname], $_SERVER[$envname]);
        }

        $enabled = enrol_get_plugins(true);
        $enabled['mercadopagocpro'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        set_config('accesstoken', 'TEST-ACCESS-TOKEN', 'enrol_mercadopagocpro');
        set_config('publickey', 'TEST-PUBLIC-KEY', 'enrol_mercadopagocpro');
        set_config('webhooksecret', 'TEST-WEBHOOK-SECRET', 'enrol_mercadopagocpro');
        set_config('environment', 'production', 'enrol_mercadopagocpro');
        set_config('currency', 'ARS', 'enrol_mercadopagocpro');

        // Keep notifications out of the real message tables during tests.
        if (!$this->messagesredirected) {
            $this->redirectMessages();
            $this->messagesredirected = true;
        }

        sdk::register();
        $this->mpclient = new mock_http_client();
        MercadoPagoConfig::setHttpClient($this->mpclient);

        return $this->mpclient;
    }

    /**
     * Create a course with one Mercado Pago enrolment instance.
     *
     * @param  array $fields instance overrides
     * @return array{0:\stdClass,1:\stdClass} course and enrol instance
     */
    protected function create_course_with_instance(array $fields = []): array
    {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('mercadopagocpro');

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $fields = array_merge(
            [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 100,
            'currency' => 'ARS',
            'roleid' => $studentrole->id,
            ], $fields
        );

        $instanceid = $plugin->add_instance($course, $fields);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        return [$course, $instance];
    }

    /**
     * Build the x-signature header Mercado Pago would send for a notification.
     *
     * @param  string   $dataid
     * @param  string   $requestid
     * @param  string   $secret
     * @param  int|null $ts
     * @return string
     */
    protected function build_signature(string $dataid, string $requestid, string $secret, ?int $ts = null): string
    {
        $ts = $ts ?? time();
        $manifest = 'id:' . $dataid . ';request-id:' . $requestid . ';ts:' . $ts . ';';
        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, $secret);
    }
}
