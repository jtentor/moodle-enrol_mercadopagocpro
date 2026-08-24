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

use enrol_mpcheckoutpro\event\preference_created;

/**
 * Creates the Checkout Pro preference and hands back the URL the buyer is sent to.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkout_service {

    /**
     * Constructor.
     *
     * @param api_client|null $client injected in tests
     */
    public function __construct(
        /** @var api_client|null */
        protected ?api_client $client = null,
    ) {
    }

    /**
     * Start (or resume) a checkout for a user on an enrolment instance.
     *
     * @param \stdClass $instance enrol instance
     * @param \stdClass $user buyer
     * @return array{transaction:\stdClass,redirecturl:string}
     * @throws \moodle_exception on any condition that prevents the purchase
     */
    public function start(\stdClass $instance, \stdClass $user): array {
        global $DB;

        $settings = instance_settings::from_instance($instance);
        $this->validate($instance, $user, $settings);

        // Reuse a still valid preference so a double click does not create two.
        $existing = transaction::get_reusable((int)$instance->id, (int)$user->id, $settings);
        if ($existing !== null) {
            return ['transaction' => $existing, 'redirecturl' => (string)$existing->initpoint];
        }

        if (!rate_limiter::for_checkout()->allow('user_' . $user->id)) {
            throw new \moodle_exception('error:ratelimited', 'enrol_mpcheckoutpro');
        }

        $course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
        $credentials = credentials::resolve($instance);
        if (!$credentials->is_usable()) {
            throw new \moodle_exception('error:nocredentials', 'enrol_mpcheckoutpro');
        }

        $transaction = transaction::create($instance, $user, $settings);
        $builder = new preference_builder($instance, $course, $user, $transaction, $settings);
        $request = $builder->build();

        $client = $this->client ?? new api_client($credentials);

        try {
            // The idempotency key ties one preference to one local transaction, so a
            // retried HTTP request can never produce a duplicate preference.
            $preference = $client->create_preference($request, 'enrol_mpcheckoutpro-' . $transaction->id);
        } catch (api_exception $e) {
            transaction::record_error((int)$transaction->id, $e->getMessage());
            throw new \moodle_exception('error:preferencefailed', 'enrol_mpcheckoutpro', '', null, $e->getMessage());
        }

        $initpoint = $this->pick_init_point($preference, $credentials);
        if ($initpoint === '') {
            transaction::record_error((int)$transaction->id, 'The API response carried no init_point.');
            throw new \moodle_exception('error:noinitpoint', 'enrol_mpcheckoutpro');
        }

        $transaction = transaction::set_preference(
            (int)$transaction->id,
            (string)($preference->id ?? ''),
            $initpoint,
            $request['metadata'] ?? []
        );

        preference_created::create_from_transaction($instance, $transaction)->trigger();

        return ['transaction' => $transaction, 'redirecturl' => $initpoint];
    }

    /**
     * The URL the buyer is redirected to.
     *
     * Always init_point, in every environment. What makes a checkout a test
     * checkout is the credentials the preference was created with plus a test
     * buyer account - not a different URL. The current Mercado Pago testing guide
     * never mentions sandbox_init_point: it says to create test users and open the
     * normal checkout in an incognito window. The sandbox_init_point field is
     * still returned by the API but sends the buyer to sandbox.mercadopago.com,
     * which redirect-loops (ERR_TOO_MANY_REDIRECTS).
     *
     * @param object $preference
     * @param credentials $credentials
     * @return string
     * @see https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/integration-test/test-purchases
     */
    protected function pick_init_point(object $preference, credentials $credentials): string {
        unset($credentials);
        return (string)($preference->init_point ?? '');
    }

    /**
     * Refuse to start a checkout that could not result in a valid enrolment.
     *
     * @param \stdClass $instance
     * @param \stdClass $user
     * @param instance_settings $settings
     * @return void
     * @throws \moodle_exception
     */
    protected function validate(\stdClass $instance, \stdClass $user, instance_settings $settings): void {
        global $DB;

        if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
            throw new \moodle_exception('error:instancedisabled', 'enrol_mpcheckoutpro');
        }
        if (isguestuser($user) || !isloggedin()) {
            throw new \moodle_exception('error:mustbeloggedin', 'enrol_mpcheckoutpro');
        }
        if ($settings->cost <= 0) {
            throw new \moodle_exception('error:nocost', 'enrol_mpcheckoutpro');
        }
        if (!in_array($settings->currency, util::supported_currencies(), true)) {
            throw new \moodle_exception('error:unsupportedcurrency', 'enrol_mpcheckoutpro', '', $settings->currency);
        }
        if (!util::site_is_https()) {
            throw new \moodle_exception('error:httpsrequired', 'enrol_mpcheckoutpro');
        }
        if (!sdk::is_available()) {
            throw new \moodle_exception('error:sdkmissing', 'enrol_mpcheckoutpro');
        }

        $now = time();
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > $now) {
            throw new \moodle_exception('error:enrolmentnotopen', 'enrol_mpcheckoutpro');
        }
        if ($instance->enrolenddate != 0 && $instance->enrolenddate < $now) {
            throw new \moodle_exception('error:enrolmentclosed', 'enrol_mpcheckoutpro');
        }

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        if ($ue && (int)$ue->status === ENROL_USER_ACTIVE) {
            throw new \moodle_exception('error:alreadyenrolled', 'enrol_mpcheckoutpro');
        }

        if (!enrolment_manager::has_capacity($instance, $settings)) {
            throw new \moodle_exception('error:coursefull', 'enrol_mpcheckoutpro');
        }

        if ($settings->marketplaceenabled && $settings->marketplacefee > 0
                && $settings->marketplacefee >= $settings->cost) {
            throw new \moodle_exception('error:feetoolarge', 'enrol_mpcheckoutpro');
        }
    }
}
