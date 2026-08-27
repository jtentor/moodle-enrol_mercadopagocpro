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

namespace enrol_mercadopagocpro\tests\fixtures;

use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;

/**
 * In-memory HTTP client that answers Mercado Pago API calls from a queue of
 * canned responses, so the test suite never reaches the network.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_http_client implements MPHttpClient
{

    /**
     * @var array<int,array{status:int,body:array}> Queued responses. 
     */
    protected array $queue = [];

    /**
     * @var array<int,array{method:string,uri:string,payload:?array,headers:?array}> Requests seen. 
     */
    public array $requests = [];

    /**
     * Queue one response.
     *
     * @param  array $body   decoded JSON body
     * @param  int   $status HTTP status
     * @return self
     */
    public function push(array $body, int $status = 200): self
    {
        $this->queue[] = ['status' => $status, 'body' => $body];
        return $this;
    }

    /**
     * Queue a Checkout Pro preference response.
     *
     * @param  string $id        preference id
     * @param  string $initpoint
     * @return self
     */
    public function push_preference(string $id = 'PREF-1', string $initpoint = 'https://mp.test/checkout/PREF-1'): self
    {
        return $this->push(
            [
            'id' => $id,
            'init_point' => $initpoint,
            'sandbox_init_point' => $initpoint . '?sandbox=1',
            'collector_id' => 1234567,
            'date_created' => '2026-08-21T10:00:00.000-03:00',
            ]
        );
    }

    /**
     * Queue a payment response.
     *
     * @param  array $overrides fields to override on the default payment body
     * @return self
     */
    public function push_payment(array $overrides = []): self
    {
        return $this->push(
            array_merge(
                [
                'id' => 1122334455,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'external_reference' => '',
                'transaction_amount' => 100.00,
                'currency_id' => 'ARS',
                'payment_method_id' => 'visa',
                'payment_type_id' => 'credit_card',
                'installments' => 1,
                'live_mode' => true,
                'date_approved' => '2026-08-21T10:05:00.000-03:00',
                'order' => ['id' => 99887766, 'type' => 'mercadopago'],
                ], $overrides
            )
        );
    }

    /**
     * Answer a request from the queue.
     *
     * @param  MPRequest $request
     * @return MPResponse
     * @throws MPApiException when the queued status is not 2xx
     */
    public function send(MPRequest $request): MPResponse
    {
        $payload = $request->getPayload();
        $this->requests[] = [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'payload' => $payload !== null ? json_decode($payload, true) : null,
            'headers' => $request->getHeaders(),
        ];

        if (!$this->queue) {
            throw new \RuntimeException('mock_http_client ran out of queued responses for ' . $request->getUri());
        }

        $next = array_shift($this->queue);
        $response = new MPResponse($next['status'], $next['body']);

        if ($next['status'] < 200 || $next['status'] >= 300) {
            throw new MPApiException('Mocked API error', $response);
        }

        return $response;
    }

    /**
     * The body of the last request that was sent.
     *
     * @return array|null
     */
    public function last_payload(): ?array
    {
        $last = end($this->requests);
        return $last ? $last['payload'] : null;
    }

    /**
     * The URI of the last request that was sent.
     *
     * @return string
     */
    public function last_uri(): string
    {
        $last = end($this->requests);
        return $last ? $last['uri'] : '';
    }
}
