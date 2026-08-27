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

/**
 * Builds the body of POST /checkout/preferences.
 *
 * Every field emitted here appears in the official Create preference reference for
 * Checkout Pro. Nothing else is sent.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro/preferences/create-preference/post
 * @see       https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/configure-back-urls
 */
class preference_builder
{
    /**
     * @var string Only value documented for auto_return.
     */
    public const AUTO_RETURN_APPROVED = 'approved';

    /**
     * @var string purpose value that restricts the checkout to logged in Mercado Pago accounts.
     */
    public const PURPOSE_WALLET_PURCHASE = 'wallet_purchase';

    /**
     * Constructor.
     *
     * @param \stdClass         $instance    enrol instance record
     * @param \stdClass         $course      course record
     * @param \stdClass         $user        buyer
     * @param \stdClass         $transaction the local transaction record (already has an id)
     * @param instance_settings $settings    resolved instance settings
     */
    public function __construct(
        /**
         * @var \stdClass
         */
        protected \stdClass $instance,
        /**
         * @var \stdClass
         */
        protected \stdClass $course,
        /**
         * @var \stdClass
         */
        protected \stdClass $user,
        /**
         * @var \stdClass
         */
        protected \stdClass $transaction,
        /**
         * @var instance_settings
         */
        protected instance_settings $settings,
    ) {
    }

    /**
     * Produce the preference body.
     *
     * @return array
     */
    public function build(): array {
        $request = [
            'items' => [$this->build_item()],
            'payer' => $this->build_payer(),
            'back_urls' => $this->build_back_urls(),
            'notification_url' => $this->build_notification_url(),
            'external_reference' => $this->transaction->externalreference,
            'binary_mode' => $this->settings->binarymode,
            'metadata' => $this->build_metadata(),
        ];

        // The auto_return field only makes sense with a success back_url, which we always send.
        if ($this->settings->autoreturn) {
            $request['auto_return'] = self::AUTO_RETURN_APPROVED;
        }

        $descriptor = $this->sanitise_descriptor($this->settings->statementdescriptor);
        if ($descriptor !== '') {
            $request['statement_descriptor'] = $descriptor;
        }

        // Restricting the checkout to registered Mercado Pago accounts is what
        // makes account money and saved cards available. The cost is that guests
        // cannot pay at all, and cash and bank transfer disappear.
        if ($this->settings->walletpurchase) {
            $request['purpose'] = self::PURPOSE_WALLET_PURCHASE;
        }

        $paymentmethods = $this->build_payment_methods();
        if ($paymentmethods !== []) {
            $request['payment_methods'] = $paymentmethods;
        }

        $expiration = $this->build_expiration();
        if ($expiration !== []) {
            $request += $expiration;
        }

        // Split payments: the preference is created with the seller's access token and
        // marketplace_fee carries the marketplace commission.
        if ($this->settings->marketplaceenabled && $this->settings->marketplacefee > 0) {
            $request['marketplace_fee'] = util::normalise_amount($this->settings->marketplacefee);
        }
        if ($this->settings->marketplaceenabled && $this->settings->marketplacename !== '') {
            $request['marketplace'] = $this->settings->marketplacename;
        }

        return $request;
    }

    /**
     * The single item representing the course enrolment.
     *
     * @return array
     */
    protected function build_item(): array {
        $context = \context_course::instance($this->course->id);
        $title = format_string($this->course->fullname, true, ['context' => $context]);
        $description = $this->settings->itemdescription !== ''
            ? $this->settings->itemdescription
            : get_string('itemdescription_default', 'enrol_mercadopagocpro', $title);

        return [
            'id' => 'enrol_mercadopagocpro-' . $this->instance->id,
            'title' => $this->truncate($title, 256),
            'description' => $this->truncate($description, 600),
            'category_id' => $this->settings->categoryid !== '' ? $this->settings->categoryid : 'learnings',
            'quantity' => 1,
            'currency_id' => $this->settings->currency,
            'unit_price' => util::normalise_amount($this->settings->cost),
        ];
    }

    /**
     * Payer block. Only the fields Moodle actually knows are sent.
     *
     * @return array
     */
    protected function build_payer(): array {
        $payer = [
            'email' => $this->user->email,
        ];
        if (!empty($this->user->firstname)) {
            $payer['name'] = $this->truncate($this->user->firstname, 128);
        }
        if (!empty($this->user->lastname)) {
            $payer['surname'] = $this->truncate($this->user->lastname, 128);
        }
        return $payer;
    }

    /**
     * The three documented back URLs. All of them point at the plugin's return
     * handler, which re-queries the API before showing anything to the buyer.
     *
     * @return array
     */
    protected function build_back_urls(): array {
        $base = util::plugin_url('return.php', ['txn' => $this->transaction->id]);
        return [
            'success' => (new \moodle_url($base, ['result' => 'success']))->out(false),
            'pending' => (new \moodle_url($base, ['result' => 'pending']))->out(false),
            'failure' => (new \moodle_url($base, ['result' => 'failure']))->out(false),
        ];
    }

    /**
     * Webhook endpoint. Mercado Pago appends its own query parameters to this URL
     * and preserves the ones we set, so the enrolment instance is carried along.
     * That is what lets the endpoint pick the right webhook secret before it has
     * made any API call.
     *
     * @return string
     */
    protected function build_notification_url(): string {
        return util::plugin_url('webhook.php', ['enrolid' => (int)$this->instance->id])->out(false);
    }

    /**
     * payment_methods block built from the plugin settings.
     *
     * @return array
     */
    protected function build_payment_methods(): array {
        $block = [];

        $excludedtypes = $this->settings->excludedpaymenttypes;
        if ($excludedtypes) {
            $block['excluded_payment_types'] = array_values(
                array_map(
                    static fn(string $id): array => ['id' => $id],
                    $excludedtypes
                )
            );
        }

        $excludedmethods = $this->settings->excludedpaymentmethods;
        if ($excludedmethods) {
            $block['excluded_payment_methods'] = array_values(
                array_map(
                    static fn(string $id): array => ['id' => $id],
                    $excludedmethods
                )
            );
        }

        if ($this->settings->installments > 0) {
            $block['installments'] = $this->settings->installments;
        }
        if ($this->settings->defaultinstallments > 0) {
            $block['default_installments'] = $this->settings->defaultinstallments;
        }
        if ($this->settings->defaultpaymentmethodid !== '') {
            $block['default_payment_method_id'] = $this->settings->defaultpaymentmethodid;
        }

        return $block;
    }

    /**
     * expires / expiration_date_from / expiration_date_to.
     *
     * @return array
     */
    protected function build_expiration(): array {
        if ($this->settings->preferenceexpiry <= 0) {
            return [];
        }
        $now = time();
        return [
            'expires' => true,
            'expiration_date_from' => self::format_datetime($now),
            'expiration_date_to' => self::format_datetime($now + $this->settings->preferenceexpiry),
        ];
    }

    /**
     * Metadata attached to the preference and echoed back on the payment.
     *
     * Keys are lower case snake_case and values are scalars only. No personal data
     * beyond the internal Moodle ids is included.
     *
     * @return array
     */
    protected function build_metadata(): array {
        global $CFG;

        $metadata = [
            'moodle_site' => parse_url($CFG->wwwroot, PHP_URL_HOST) ?: 'moodle',
            'moodle_component' => 'enrol_mercadopagocpro',
            'moodle_txn_id' => (int)$this->transaction->id,
            'moodle_enrol_id' => (int)$this->instance->id,
            'moodle_course_id' => (int)$this->course->id,
            'moodle_course_shortname' => $this->truncate((string)$this->course->shortname, 100),
            'moodle_user_id' => (int)$this->user->id,
            'enrolment_period' => (int)$this->settings->enrolperiod,
            'plugin_release' => 'v1.0.0',
        ];

        foreach ($this->settings->custommetadata as $key => $value) {
            $key = strtolower(preg_replace('/[^a-z0-9_]/i', '_', (string)$key));
            if ($key === '' || isset($metadata[$key])) {
                continue;
            }
            if (is_scalar($value)) {
                $metadata[$key] = is_string($value) ? $this->truncate($value, 250) : $value;
            }
        }

        return $metadata;
    }

    /**
     * Format a unix timestamp the way Mercado Pago expects expiration dates
     * (ISO 8601 with milliseconds and an explicit offset).
     *
     * @param  int $timestamp
     * @return string
     */
    public static function format_datetime(int $timestamp): string {
        $date = new \DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone(\core_date::get_server_timezone_object());
        return $date->format('Y-m-d\TH:i:s.v') . $date->format('P');
    }

    /**
     * statement_descriptor accepts a short alphanumeric string.
     *
     * @param  string $value
     * @return string
     */
    protected function sanitise_descriptor(string $value): string {
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $value);
        return $this->truncate(trim((string)$value), 22);
    }

    /**
     * Multibyte safe truncation.
     *
     * @param  string $value
     * @param  int    $length
     * @return string
     */
    protected function truncate(string $value, int $length): string {
        return \core_text::substr($value, 0, $length);
    }
}
