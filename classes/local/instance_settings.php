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
 * Resolved configuration for one enrolment instance.
 *
 * Merges the site level plugin settings with the per instance overrides stored in
 * the standard custom columns of the enrol table:
 *
 *   customint1  group to assign on approval (0 = none)
 *   customint2  maximum installments (0 = use site setting)
 *   customint3  create a holding enrolment for pending payments (-1 = site setting)
 *   customint4  course welcome message send option (ENROL_SEND_EMAIL_FROM_*),
 *               matching the column enrol_self uses for the same purpose
 *   customint5  maximum number of enrolled users (0 = unlimited)
 *   customint6  action on refund / chargeback (-1 = site setting)
 *   customint7  default installments (0 = none)
 *   customint8  split payments enabled for this instance
 *   customchar1 short description shown on the enrolment page
 *   customchar2 default_payment_method_id
 *   customchar3 Mercado Pago seller (collector) id used for split payments
 *   customdec1  marketplace_fee
 *   customtext1 custom course welcome message, again matching enrol_self
 *   customtext2 JSON blob with excluded payment types / methods, item description,
 *               category_id, extra metadata fields and the payment notification
 *               toggle - the int columns are all spoken for
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_settings
{
    /**
     * @var int Reversal action: leave the enrolment untouched.
     */
    public const REVERSAL_KEEP = 0;
    /**
     * @var int Reversal action: suspend the enrolment and remove the roles.
     */
    public const REVERSAL_SUSPEND = 1;
    /**
     * @var int Reversal action: unenrol the user.
     */
    public const REVERSAL_UNENROL = 2;

    /**
     * @var float Enrolment cost.
     */
    public float $cost = 0.0;
    /**
     * @var string ISO-4217 currency.
     */
    public string $currency = 'ARS';
    /**
     * @var int Role assigned on approval.
     */
    public int $roleid = 0;
    /**
     * @var int Enrolment duration in seconds, 0 = unlimited.
     */
    public int $enrolperiod = 0;
    /**
     * @var int Enrolment start date, 0 = immediately.
     */
    public int $enrolstartdate = 0;
    /**
     * @var int Enrolment end date, 0 = never.
     */
    public int $enrolenddate = 0;
    /**
     * @var int Group to add the user to, 0 = none.
     */
    public int $groupid = 0;
    /**
     * @var int Maximum installments offered, 0 = do not send the field.
     */
    public int $installments = 0;
    /**
     * @var int default_installments, 0 = do not send the field.
     */
    public int $defaultinstallments = 0;
    /**
     * @var string default_payment_method_id, empty = do not send the field.
     */
    public string $defaultpaymentmethodid = '';
    /**
     * @var string[] excluded_payment_types ids.
     */
    public array $excludedpaymenttypes = [];
    /**
     * @var string[] excluded_payment_methods ids.
     */
    public array $excludedpaymentmethods = [];
    /**
     * @var bool binary_mode.
     */
    public bool $binarymode = false;
    /**
     * @var bool Send purpose=wallet_purchase, restricting checkout to Mercado Pago accounts.
     */
    public bool $walletpurchase = false;
    /**
     * @var bool Whether to send auto_return=approved.
     */
    public bool $autoreturn = true;
    /**
     * @var string statement_descriptor.
     */
    public string $statementdescriptor = '';
    /**
     * @var int Preference validity in seconds, 0 = no expiration fields.
     */
    public int $preferenceexpiry = 0;
    /**
     * @var string Item description.
     */
    public string $itemdescription = '';
    /**
     * @var string Item category_id.
     */
    public string $categoryid = 'learnings';
    /**
     * @var array Extra metadata key => value.
     */
    public array $custommetadata = [];
    /**
     * @var bool Create a suspended enrolment while a payment is pending.
     */
    public bool $pendingholding = false;
    /**
     * @var bool Send notifications about payment events.
     */
    public bool $notifications = true;
    /**
     * @var int The raw tri-state behind $notifications: -1 site default, 0 no, 1 yes.
     */
    public int $notificationsraw = -1;
    /**
     * @var int Course welcome message send option, one of ENROL_SEND_EMAIL_FROM_*.
     */
    public int $welcomemessage = ENROL_DO_NOT_SEND_EMAIL;
    /**
     * @var string Custom course welcome message, empty for the core default text.
     */
    public string $welcomemessagetext = '';
    /**
     * @var int Action on refund / chargeback, one of the self::REVERSAL_* constants.
     */
    public int $reversalaction = self::REVERSAL_SUSPEND;
    /**
     * @var int Maximum enrolled users, 0 = unlimited.
     */
    public int $maxenrolled = 0;
    /**
     * @var bool Split payments (marketplace) mode.
     */
    public bool $marketplaceenabled = false;
    /**
     * @var float marketplace_fee.
     */
    public float $marketplacefee = 0.0;
    /**
     * @var string Seller (collector) id.
     */
    public string $sellerid = '';
    /**
     * @var string marketplace identifier sent in the preference.
     */
    public string $marketplacename = '';

    /**
     * Build the resolved settings for an enrolment instance.
     *
     * @param  \stdClass $instance enrol instance record
     * @return self
     */
    public static function from_instance(\stdClass $instance): self {
        $s = new self();
        $config = static fn(string $name, $default = null) => get_config('enrol_mercadopagocpro', $name) !== false
            ? get_config('enrol_mercadopagocpro', $name)
            : $default;

        $s->cost = (float)($instance->cost ?? 0);
        if ($s->cost <= 0) {
            $s->cost = (float)$config('cost', 0);
        }
        $s->currency = !empty($instance->currency) ? (string)$instance->currency : (string)$config('currency', 'ARS');
        $s->roleid = (int)($instance->roleid ?? 0);
        $s->enrolperiod = (int)($instance->enrolperiod ?? 0);
        $s->enrolstartdate = (int)($instance->enrolstartdate ?? 0);
        $s->enrolenddate = (int)($instance->enrolenddate ?? 0);

        $s->groupid = (int)($instance->customint1 ?? 0);
        $s->maxenrolled = (int)($instance->customint5 ?? 0);

        $s->installments = (int)($instance->customint2 ?? 0);
        if ($s->installments <= 0) {
            $s->installments = (int)$config('installments', 0);
        }
        $s->defaultinstallments = (int)($instance->customint7 ?? 0);
        if ($s->defaultinstallments <= 0) {
            $s->defaultinstallments = (int)$config('defaultinstallments', 0);
        }
        // The default_installments value can never exceed the offered installments.
        if ($s->installments > 0 && $s->defaultinstallments > $s->installments) {
            $s->defaultinstallments = $s->installments;
        }

        $holding = (int)($instance->customint3 ?? -1);
        $s->pendingholding = $holding < 0 ? (bool)$config('pendingholding', 0) : (bool)$holding;

        $s->welcomemessage = (int)($instance->customint4 ?? ENROL_DO_NOT_SEND_EMAIL);
        $s->welcomemessagetext = trim((string)($instance->customtext1 ?? ''));

        $reversal = (int)($instance->customint6 ?? -1);
        $s->reversalaction = $reversal < 0
            ? (int)$config('reversalaction', self::REVERSAL_SUSPEND)
            : $reversal;

        $s->binarymode = (bool)$config('binarymode', 0);
        $s->walletpurchase = (bool)$config('walletpurchase', 0);
        $s->autoreturn = (bool)$config('autoreturn', 1);
        $s->statementdescriptor = trim((string)$config('statementdescriptor', ''));
        $s->preferenceexpiry = (int)$config('preferenceexpiry', 0);

        $s->defaultpaymentmethodid = trim((string)($instance->customchar2 ?? ''));
        if ($s->defaultpaymentmethodid === '') {
            $s->defaultpaymentmethodid = trim((string)$config('defaultpaymentmethodid', ''));
        }

        $s->excludedpaymenttypes = self::split_ids((string)$config('excludedpaymenttypes', ''));
        $s->excludedpaymentmethods = self::split_ids((string)$config('excludedpaymentmethods', ''));
        $s->custommetadata = self::parse_metadata_lines((string)$config('custommetadata', ''));

        // Instance level JSON overrides.
        $extra = self::decode_json((string)($instance->customtext2 ?? ''));

        $s->notificationsraw = isset($extra['notifications']) ? (int)$extra['notifications'] : -1;
        $s->notifications = $s->notificationsraw < 0
            ? (bool)$config('notifications', 1)
            : (bool)$s->notificationsraw;
        if (isset($extra['excludedpaymenttypes']) && is_array($extra['excludedpaymenttypes'])) {
            $s->excludedpaymenttypes = self::clean_ids($extra['excludedpaymenttypes']);
        }
        if (isset($extra['excludedpaymentmethods']) && is_array($extra['excludedpaymentmethods'])) {
            $s->excludedpaymentmethods = self::clean_ids($extra['excludedpaymentmethods']);
        }
        if (isset($extra['itemdescription'])) {
            $s->itemdescription = trim((string)$extra['itemdescription']);
        }
        if (!empty($extra['categoryid'])) {
            $s->categoryid = preg_replace('/[^a-z0-9_]/i', '', (string)$extra['categoryid']);
        }
        if (isset($extra['metadata']) && is_array($extra['metadata'])) {
            foreach ($extra['metadata'] as $key => $value) {
                if (is_scalar($value)) {
                    $s->custommetadata[(string)$key] = $value;
                }
            }
        }
        if ($s->itemdescription === '') {
            $s->itemdescription = trim((string)($instance->customchar1 ?? ''));
        }

        // Split payments.
        $s->marketplaceenabled = (bool)$config('marketplaceenabled', 0) && (bool)($instance->customint8 ?? 0);
        $s->marketplacefee = (float)($instance->customdec1 ?? 0);
        $s->sellerid = trim((string)($instance->customchar3 ?? ''));
        $s->marketplacename = trim((string)$config('marketplacename', ''));

        return $s;
    }

    /**
     * Split a comma separated list of ids.
     *
     * @param  string $value
     * @return string[]
     */
    private static function split_ids(string $value): array {
        if (trim($value) === '') {
            return [];
        }
        return self::clean_ids(explode(',', $value));
    }

    /**
     * Normalise and validate a list of Mercado Pago ids.
     *
     * @param  array $ids
     * @return string[]
     */
    private static function clean_ids(array $ids): array {
        $clean = [];
        foreach ($ids as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            $id = strtolower(trim((string)$id));
            // Mercado Pago ids are short lowercase alphanumeric tokens.
            if ($id !== '' && preg_match('/^[a-z0-9_]{1,32}$/', $id)) {
                $clean[$id] = $id;
            }
        }
        return array_values($clean);
    }

    /**
     * Parse "key=value" lines into an array.
     *
     * @param  string $value
     * @return array
     */
    private static function parse_metadata_lines(string $value): array {
        $result = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            if ($key !== '') {
                $result[$key] = trim($val);
            }
        }
        return $result;
    }

    /**
     * Decode a JSON blob, returning an empty array when it is not usable.
     *
     * @param  string $json
     * @return array
     */
    private static function decode_json(string $json): array {
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode the instance level JSON blob from form data.
     *
     * @param  array $values
     * @return string
     */
    public static function encode_extra(array $values): string {
        $filtered = array_filter($values, static fn($v) => $v !== null && $v !== '' && $v !== []);
        if ($filtered === []) {
            return '';
        }
        return (string)json_encode($filtered, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
