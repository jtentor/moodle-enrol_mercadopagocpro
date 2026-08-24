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

use enrol_mpcheckoutpro\event\webhook_received;
use enrol_mpcheckoutpro\event\webhook_rejected;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;

/**
 * Receives, validates and dispatches Mercado Pago webhook notifications.
 *
 * Notification format, headers and the signature algorithm all follow the
 * official Webhooks documentation. The body is never trusted for payment state:
 * it only tells us which resource to go and read from the API.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks
 */
class webhook_handler
{

    /**
     * @var string Table holding the webhook audit log. 
     */
    public const LOG_TABLE = 'enrol_mpcheckoutpro_wh';

    /**
     * @var string Signature was verified. 
     */
    public const SIG_VALID = 'valid';
    /**
     * @var string Signature present but wrong. 
     */
    public const SIG_INVALID = 'invalid';
    /**
     * @var string No x-signature header was sent. 
     */
    public const SIG_MISSING = 'missing';
    /**
     * @var string Verification deliberately skipped (no secret configured). 
     */
    public const SIG_SKIPPED = 'skipped';

    /**
     * @var int Maximum accepted body size, well above any real notification. 
     */
    private const MAX_BODY_BYTES = 65536;

    /**
     * @var int[] Retry backoff in seconds for deferred processing. 
     */
    private const RETRY_BACKOFF = [60, 300, 900, 3600, 10800];

    /**
     * @var int Give up after this many internal retries. 
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Constructor.
     *
     * @param payment_processor|null $processor injected in tests
     */
    public function __construct(
        /**
         * @var payment_processor|null 
         */
        protected ?payment_processor $processor = null,
    ) {
    }

    /**
     * Handle one incoming notification.
     *
     * @param  array  $query    the request query string parameters
     * @param  array  $headers  request headers, lower case keys
     * @param  string $rawbody  raw request body
     * @param  string $clientip remote address, used for rate limiting
     * @return array{status:int,body:string} the HTTP response to send back
     */
    public function handle(array $query, array $headers, string $rawbody, string $clientip): array
    {
        if (!rate_limiter::for_webhook()->allow($clientip)) {
            util::log_error('Webhook rate limit exceeded', ['ip' => $clientip]);
            return ['status' => 429, 'body' => 'Too Many Requests'];
        }

        if (strlen($rawbody) > self::MAX_BODY_BYTES) {
            return ['status' => 413, 'body' => 'Payload Too Large'];
        }

        $body = json_decode($rawbody, true);
        if (!is_array($body)) {
            $body = [];
        }

        $notification = $this->extract_notification($query, $body);
        $logid = $this->log_reception($notification, $rawbody, $headers);

        // Resolve the enrolment instance carried in our own notification_url query
        // parameter, so the right webhook secret is used before any API call.
        $instance = $this->resolve_instance($query);
        $credentials = credentials::resolve($instance);

        $signaturestatus = $this->verify_signature($headers, $notification, $credentials);
        $this->update_log($logid, ['signaturestatus' => $signaturestatus]);

        if ($signaturestatus === self::SIG_INVALID || $signaturestatus === self::SIG_MISSING) {
            if ($this->signature_required()) {
                webhook_rejected::create_from_notification($notification, $signaturestatus)->trigger();
                $this->finish_log($logid, 401, false, 'Signature ' . $signaturestatus);
                return ['status' => 401, 'body' => 'Unauthorized'];
            }
            util::log_error(
                'Webhook signature could not be verified but verification is not enforced', [
                'reason' => $signaturestatus,
                'requestid' => $notification['requestid'],
                ]
            );
        }

        webhook_received::create_from_notification($notification)->trigger();

        // Link the audit row to the transaction as soon as one can be identified,
        // so the log is useful even when processing is deferred or fails.
        $transaction = $this->find_transaction($notification, $instance);
        if ($transaction !== null) {
            $this->update_log($logid, ['txnid' => (int)$transaction->id]);
        }

        if ($this->is_duplicate($notification)) {
            $this->finish_log($logid, 200, true, 'Duplicate notification ignored.');
            return ['status' => 200, 'body' => 'OK'];
        }

        if ($this->deferred_mode()) {
            // Acknowledge immediately and let the scheduled task do the API work.
            $this->update_log($logid, ['nextretry' => time(), 'attempts' => 0]);
            $this->finish_log($logid, 200, false, 'Deferred for background processing.');
            return ['status' => 200, 'body' => 'OK'];
        }

        $result = $this->dispatch($notification, $instance, $transaction);

        if ($result->should_retry()) {
            // Accept the notification and retry internally, so Mercado Pago is not
            // kept waiting and the transaction still converges.
            $this->schedule_retry($logid, 0);
            $this->finish_log($logid, 200, false, $result->message);
            return ['status' => 200, 'body' => 'OK'];
        }

        $this->finish_log($logid, 200, true, $result->message);
        return ['status' => 200, 'body' => 'OK'];
    }

    /**
     * Dispatch a notification to the payment processor.
     *
     * @param  array          $notification normalised notification
     * @param  \stdClass|null $instance
     * @param  \stdClass|null $transaction  already resolved transaction, when the caller has one
     * @return processing_result
     */
    public function dispatch(
        array $notification,
        ?\stdClass $instance = null,
        ?\stdClass $transaction = null,
    ): processing_result {
        $processor = $this->processor ?? new payment_processor();
        $dataid = (string)$notification['dataid'];
        $type = (string)$notification['type'];

        if ($dataid === '') {
            return processing_result::ignored('Notification carries no data.id.');
        }

        $transaction = $transaction ?? $this->find_transaction($notification, $instance);

        try {
            if ($type === 'payment') {
                return $processor->process_payment($dataid, $transaction);
            }
            if ($type === 'merchant_order' || $type === 'topic_merchant_order_wh') {
                return $processor->process_merchant_order($dataid, $transaction);
            }
        } catch (\Throwable $e) {
            util::log_error(
                'Webhook dispatch failed: ' . $e->getMessage(), [
                'type' => $type,
                'dataid' => $dataid,
                ]
            );
            return processing_result::retry($e->getMessage(), true);
        }

        return processing_result::ignored('Notification type "' . $type . '" is not handled by this plugin.');
    }

    /**
     * Best effort resolution of the transaction a notification refers to.
     *
     * @param  array          $notification
     * @param  \stdClass|null $instance
     * @return \stdClass|null
     */
    protected function find_transaction(array $notification, ?\stdClass $instance): ?\stdClass
    {
        global $DB;

        if ($notification['type'] === 'payment' && $notification['dataid'] !== '') {
            $existing = transaction::get_by_payment_id($notification['dataid']);
            if ($existing !== null) {
                return $existing;
            }
        }

        if ($instance !== null) {
            // Newest transaction of this instance that is still waiting for a payment.
            [$insql, $params] = $DB->get_in_or_equal(status::transitional(), SQL_PARAMS_NAMED, 'st');
            $params['enrolid'] = $instance->id;
            $records = $DB->get_records_select(
                transaction::TABLE,
                "enrolid = :enrolid AND status $insql AND paymentid IS NULL",
                $params,
                'id DESC',
                '*',
                0,
                1
            );
            if ($records) {
                return reset($records);
            }
        }

        return null;
    }

    /**
     * Normalise the notification into the handful of values this plugin uses.
     *
     * Mercado Pago sends the resource id both as a `data.id` query parameter and in
     * the body, and the topic either as `type` (body / query) or as the legacy
     * `topic` query parameter.
     *
     * @param  array $query
     * @param  array $body
     * @return array{dataid:string,type:string,action:string,notificationid:string,requestid:string,livemode:bool}
     */
    public function extract_notification(array $query, array $body): array
    {
        $dataid = '';
        if (isset($query['data.id'])) {
            $dataid = (string)$query['data.id'];
        } else if (isset($query['data_id'])) {
            $dataid = (string)$query['data_id'];
        } else if (isset($body['data']['id'])) {
            $dataid = (string)$body['data']['id'];
        } else if (isset($query['id'])) {
            $dataid = (string)$query['id'];
        }

        $type = '';
        if (!empty($body['type'])) {
            $type = (string)$body['type'];
        } else if (!empty($query['type'])) {
            $type = (string)$query['type'];
        } else if (!empty($query['topic'])) {
            $type = (string)$query['topic'];
        }

        return [
            'dataid' => clean_param($dataid, PARAM_ALPHANUMEXT),
            'type' => self::sanitise_token($type),
            'action' => self::sanitise_token((string)($body['action'] ?? '')),
            'notificationid' => clean_param((string)($body['id'] ?? ''), PARAM_ALPHANUMEXT),
            'requestid' => '',
            'livemode' => !empty($body['live_mode']),
        ];
    }

    /**
     * Keep only the characters Mercado Pago uses in topic and action names.
     *
     * @param  string $value
     * @return string
     */
    private static function sanitise_token(string $value): string
    {
        return (string)preg_replace('/[^A-Za-z0-9_.\-]/', '', trim($value));
    }

    /**
     * Validate the x-signature header with the official SDK validator.
     *
     * @param  array       $headers
     * @param  array       $notification passed by reference so the request id can be recorded
     * @param  credentials $credentials
     * @return string one of the SIG_* constants
     */
    protected function verify_signature(array $headers, array &$notification, credentials $credentials): string
    {
        $signature = $headers['x-signature'] ?? null;
        $requestid = $headers['x-request-id'] ?? null;
        $notification['requestid'] = (string)($requestid ?? '');

        if (!$credentials->can_validate_signature()) {
            return self::SIG_SKIPPED;
        }
        if ($signature === null || trim((string)$signature) === '') {
            return self::SIG_MISSING;
        }
        if (!sdk::is_available()) {
            util::log_error('Cannot validate the webhook signature: the Mercado Pago SDK is not installed.');
            return self::SIG_SKIPPED;
        }

        $tolerance = (int)get_config('enrol_mpcheckoutpro', 'signaturetolerance');

        try {
            WebhookSignatureValidator::validate(
                (string)$signature,
                $requestid !== null ? (string)$requestid : null,
                $notification['dataid'] !== '' ? $notification['dataid'] : null,
                $credentials->get_webhook_secret(),
                $tolerance > 0 ? $tolerance : null,
            );
            return self::SIG_VALID;
        } catch (InvalidWebhookSignatureException $e) {
            util::log_error(
                'Webhook signature rejected', [
                'reason' => $e->getReason(),
                'requestid' => $e->getRequestId(),
                ]
            );
            return self::SIG_INVALID;
        } catch (\Throwable $e) {
            util::log_error('Webhook signature validation error: ' . $e->getMessage());
            return self::SIG_INVALID;
        }
    }

    /**
     * Whether an unverifiable notification must be rejected.
     *
     * @return bool
     */
    protected function signature_required(): bool
    {
        $value = get_config('enrol_mpcheckoutpro', 'requiresignature');
        return $value === false ? true : (bool)$value;
    }

    /**
     * Whether notifications are acknowledged first and processed by the task.
     *
     * @return bool
     */
    protected function deferred_mode(): bool
    {
        return (bool)get_config('enrol_mpcheckoutpro', 'deferwebhooks');
    }

    /**
     * Resolve the enrolment instance from the enrolid query parameter we set on
     * notification_url.
     *
     * @param  array $query
     * @return \stdClass|null
     */
    protected function resolve_instance(array $query): ?\stdClass
    {
        global $DB;

        $enrolid = isset($query['enrolid']) ? (int)$query['enrolid'] : 0;
        if ($enrolid <= 0) {
            return null;
        }
        $instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'mpcheckoutpro']);
        return $instance ?: null;
    }

    /**
     * Short lived de-duplication so a burst of identical deliveries costs one API call.
     *
     * @param  array $notification
     * @return bool
     */
    protected function is_duplicate(array $notification): bool
    {
        if ($notification['dataid'] === '') {
            return false;
        }
        try {
            $cache = \cache::make('enrol_mpcheckoutpro', 'webhookdedupe');
            $key = $notification['type'] . '_' . $notification['dataid'] . '_' . $notification['action'];
            if ($cache->get($key)) {
                return true;
            }
            $cache->set($key, time());
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Insert the audit row for an incoming notification.
     *
     * @param  array  $notification
     * @param  string $rawbody
     * @param  array  $headers
     * @return int the log row id
     */
    protected function log_reception(array $notification, string $rawbody, array $headers): int
    {
        global $DB;

        $decoded = json_decode($rawbody, true);
        return (int)$DB->insert_record(
            self::LOG_TABLE, (object)[
            'notificationid' => \core_text::substr($notification['notificationid'], 0, 64) ?: null,
            'requestid' => \core_text::substr((string)($headers['x-request-id'] ?? ''), 0, 128) ?: null,
            'type' => \core_text::substr($notification['type'], 0, 64) ?: null,
            'action' => \core_text::substr($notification['action'], 0, 64) ?: null,
            'dataid' => \core_text::substr($notification['dataid'], 0, 64) ?: null,
            'signaturestatus' => 'unknown',
            'httpstatus' => 0,
            'processed' => 0,
            'attempts' => 0,
            'payload' => util::encode_for_storage(is_array($decoded) ? $decoded : ['raw' => $rawbody], 20000),
            'timecreated' => time(),
            ]
        );
    }

    /**
     * Update fields on a log row.
     *
     * @param  int   $logid
     * @param  array $fields
     * @return void
     */
    protected function update_log(int $logid, array $fields): void
    {
        global $DB;
        $fields['id'] = $logid;
        $DB->update_record(self::LOG_TABLE, (object)$fields);
    }

    /**
     * Close a log row with the response we are about to send.
     *
     * @param  int    $logid
     * @param  int    $httpstatus
     * @param  bool   $processed
     * @param  string $message
     * @return void
     */
    protected function finish_log(int $logid, int $httpstatus, bool $processed, string $message): void
    {
        $this->update_log(
            $logid, [
            'httpstatus' => $httpstatus,
            'processed' => $processed ? 1 : 0,
            'errormessage' => \core_text::substr($message, 0, 1000),
            'timeprocessed' => $processed ? time() : null,
            ]
        );
    }

    /**
     * Arrange another internal attempt at a notification.
     *
     * @param  int $logid
     * @param  int $attempts attempts already made
     * @return void
     */
    protected function schedule_retry(int $logid, int $attempts): void
    {
        $index = min($attempts, count(self::RETRY_BACKOFF) - 1);
        $this->update_log(
            $logid, [
            'attempts' => $attempts + 1,
            'nextretry' => time() + self::RETRY_BACKOFF[$index],
            ]
        );
    }

    /**
     * Log rows that are waiting for another attempt.
     *
     * @param  int $limit
     * @return \stdClass[]
     */
    public static function get_retryable(int $limit = 50): array
    {
        global $DB;
        return $DB->get_records_select(
            self::LOG_TABLE,
            'processed = 0 AND nextretry IS NOT NULL AND nextretry <= :now AND attempts < :maxattempts',
            ['now' => time(), 'maxattempts' => self::MAX_ATTEMPTS],
            'nextretry ASC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Re-attempt one logged notification. Used by the retry task.
     *
     * @param  \stdClass $logrow
     * @return processing_result
     */
    public function retry(\stdClass $logrow): processing_result
    {
        $notification = [
            'dataid' => (string)($logrow->dataid ?? ''),
            'type' => (string)($logrow->type ?? ''),
            'action' => (string)($logrow->action ?? ''),
            'notificationid' => (string)($logrow->notificationid ?? ''),
            'requestid' => (string)($logrow->requestid ?? ''),
            'livemode' => true,
        ];

        $transaction = !empty($logrow->txnid) ? transaction::get((int)$logrow->txnid) : null;
        $result = $this->dispatch($notification, null, $transaction);

        if ($result->should_retry()) {
            $this->schedule_retry((int)$logrow->id, (int)$logrow->attempts);
            $this->update_log((int)$logrow->id, ['errormessage' => \core_text::substr($result->message, 0, 1000)]);
        } else {
            $this->update_log(
                (int)$logrow->id, [
                'processed' => 1,
                'attempts' => (int)$logrow->attempts + 1,
                'nextretry' => null,
                'errormessage' => \core_text::substr($result->message, 0, 1000),
                'timeprocessed' => time(),
                ]
            );
        }

        return $result;
    }
}
