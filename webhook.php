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

/**
 * Public endpoint receiving Mercado Pago webhook notifications.
 *
 * This script is reachable without a Moodle session, so it does the minimum
 * possible before the x-signature header has been verified: rate limiting, size
 * checks and writing the audit row.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks
 */

// No session, no login, no cookies: this is a machine to machine endpoint.
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require __DIR__ . '/../../config.php';

use enrol_mercadopagocpro\local\util;
use enrol_mercadopagocpro\local\webhook_handler;

/**
 * Send a plain response and stop.
 *
 * @param  int    $status HTTP status code
 * @param  string $body   response body
 * @return void
 */
function enrol_mercadopagocpro_respond(int $status, string $body): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo $body;
}

if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    // Mercado Pago always POSTs. Anything else is a probe.
    enrol_mercadopagocpro_respond(405, 'Method Not Allowed');
    die();
}

if (!enrol_is_enabled('mercadopagocpro')) {
    enrol_mercadopagocpro_respond(503, 'Enrolment method disabled');
    die();
}

$rawbody = file_get_contents('php://input');
if ($rawbody === false) {
    $rawbody = '';
}

// Collect the headers we need in lower case, whatever the SAPI provides.
$headers = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        $headers[strtolower($name)] = $value;
    }
} else {
    foreach ($_SERVER as $key => $value) {
        if (strncmp($key, 'HTTP_', 5) === 0) {
            $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
        }
    }
}

try {
    $handler = new webhook_handler();
    $response = $handler->handle($_GET, $headers, $rawbody, (string)getremoteaddr());
    enrol_mercadopagocpro_respond($response['status'], $response['body']);
} catch (Throwable $e) {
    util::log_error('Unhandled error in the webhook endpoint: ' . $e->getMessage());
    // A 500 makes Mercado Pago retry the notification later, which is what we want.
    enrol_mercadopagocpro_respond(500, 'Internal Server Error');
}
