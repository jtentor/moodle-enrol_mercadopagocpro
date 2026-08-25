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

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\MerchantOrder\MerchantOrderClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

/**
 * Thin wrapper over the official Mercado Pago SDK clients.
 *
 * Only three endpoints are ever called, all of them documented for Checkout Pro:
 *
 *   POST /checkout/preferences   create the payment preference
 *   GET  /v1/payments/{id}       authoritative payment status
 *   GET  /merchant_orders/{id}   resolve the payments behind a merchant_order notification
 *
 * Every call carries its own access token through RequestOptions so a per course
 * seller never inherits the site credentials.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.ar/developers/en/reference/online-payments/checkout-pro/overview
 */
class api_client
{
    /**
     * Constructor.
     *
     * @param credentials $credentials credentials used for every call made through this instance
     */
    public function __construct(
        /**
         * @var credentials
         */
        protected credentials $credentials,
    ) {
        sdk::configure();
    }

    /**
     * Build the request options for one call.
     *
     * @param  string|null $idempotencykey value for the X-Idempotency-Key header
     * @return RequestOptions
     */
    protected function request_options(?string $idempotencykey = null): RequestOptions {
        $options = new RequestOptions();
        $options->setAccessToken($this->credentials->get_access_token());
        if ($idempotencykey !== null && $idempotencykey !== '') {
            $options->setCustomHeaders(['X-Idempotency-Key: ' . $idempotencykey]);
        }
        return $options;
    }

    /**
     * Create a checkout preference.
     *
     * @param  array       $request        preference body
     * @param  string|null $idempotencykey
     * @return \MercadoPago\Resources\Preference
     * @throws api_exception
     */
    public function create_preference(array $request, ?string $idempotencykey = null) {
        return $this->call(
            function () use ($request, $idempotencykey) {
                $client = new PreferenceClient();
                return $client->create($request, $this->request_options($idempotencykey));
            },
            'POST /checkout/preferences'
        );
    }

    /**
     * Fetch a preference.
     *
     * @param  string $preferenceid
     * @return \MercadoPago\Resources\Preference
     * @throws api_exception
     */
    public function get_preference(string $preferenceid) {
        return $this->call(
            function () use ($preferenceid) {
                $client = new PreferenceClient();
                return $client->get($preferenceid, $this->request_options());
            },
            'GET /checkout/preferences/' . $preferenceid
        );
    }

    /**
     * Fetch the authoritative state of a payment.
     *
     * This is the only source of truth for payment status in this plugin: nothing
     * that arrives from the browser or from a notification body is trusted.
     *
     * @param  int|string $paymentid
     * @return \MercadoPago\Resources\Payment
     * @throws api_exception
     */
    public function get_payment($paymentid) {
        $id = (int)$paymentid;
        return $this->call(
            function () use ($id) {
                $client = new PaymentClient();
                return $client->get($id, $this->request_options());
            },
            'GET /v1/payments/' . $id
        );
    }

    /**
     * Fetch a merchant order, used to resolve merchant_order notifications to payments.
     *
     * @param  int|string $merchantorderid
     * @return \MercadoPago\Resources\MerchantOrder
     * @throws api_exception
     */
    public function get_merchant_order($merchantorderid) {
        $id = (int)$merchantorderid;
        return $this->call(
            function () use ($id) {
                $client = new MerchantOrderClient();
                return $client->get($id, $this->request_options());
            },
            'GET /merchant_orders/' . $id
        );
    }

    /**
     * Run one SDK call, translating every SDK exception into an api_exception.
     *
     * @param  callable $callable
     * @param  string   $operation human readable operation name for the log
     * @return mixed
     * @throws api_exception
     */
    protected function call(callable $callable, string $operation) {
        $start = microtime(true);
        try {
            $result = $callable();
            util::log_debug(
                'Mercado Pago call succeeded',
                [
                'operation' => $operation,
                'ms' => (int)round((microtime(true) - $start) * 1000),
                'source' => $this->credentials->get_source(),
                ]
            );
            return $result;
        } catch (MPApiException $e) {
            $statuscode = $e->getStatusCode();
            // MPResponse::getContent() is declared ": array" in the SDK, so it
            // returns the decoded body and never a string. Casting it discarded
            // the whole response, logged the literal "Array" and raised a PHP
            // warning on every API error. Pass the structure through instead:
            // util::log_error() runs it through redact() and encode_for_storage(),
            // which scrub the sensitive values and cap the length.
            $body = null;
            try {
                $body = $e->getApiResponse()->getContent();
            } catch (\Throwable $ignored) {
                $body = null;
            }
            util::log_error(
                'Mercado Pago API error',
                [
                'operation' => $operation,
                'status' => $statuscode,
                'message' => $e->getMessage(),
                'body' => is_string($body) ? substr($body, 0, 1000) : $body,
                ]
            );
            throw new api_exception($e->getMessage(), $statuscode, $operation, $e);
        } catch (\Throwable $e) {
            util::log_error(
                'Mercado Pago transport error',
                [
                'operation' => $operation,
                'message' => $e->getMessage(),
                ]
            );
            throw new api_exception($e->getMessage(), 0, $operation, $e);
        }
    }

    /**
     * The credentials this client is using.
     *
     * @return credentials
     */
    public function get_credentials(): credentials {
        return $this->credentials;
    }
}
