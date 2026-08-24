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
 * Mercado Pago Checkout Pro enrolment plugin.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\single_button;
use core_enrol\output\enrol_page;
use enrol_mpcheckoutpro\local\credentials;
use enrol_mpcheckoutpro\local\instance_settings;
use enrol_mpcheckoutpro\local\sdk;
use enrol_mpcheckoutpro\local\status;
use enrol_mpcheckoutpro\local\transaction;
use enrol_mpcheckoutpro\local\util;

/**
 * Mercado Pago Checkout Pro enrolment plugin implementation.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_mpcheckoutpro_plugin extends enrol_plugin
{

    /**
     * How the method is displayed on the "Enrolment methods" page.
     *
     * @param  stdClass $instance
     * @return string
     */
    public function get_instance_name_for_management_page(stdClass $instance): string
    {
        $result = $this->get_instance_name($instance);
        if (strlen((string)$instance->customchar1)) {
            $context = context_course::instance($instance->courseid);
            $result .= html_writer::empty_tag('br') .
                html_writer::tag('em', format_string($instance->customchar1, true, ['context' => $context]));
        }
        return $result;
    }

    /**
     * Currencies this plugin can charge in.
     *
     * @return array currencycode => localised name
     */
    public function get_possible_currencies(): array
    {
        $currencies = [];
        foreach (util::supported_currencies() as $code) {
            $currencies[$code] = new lang_string($code, 'core_currencies');
        }
        uasort($currencies, static fn($a, $b) => strcmp((string)$a, (string)$b));
        return $currencies;
    }

    /**
     * Optional enrolment information icons for the course listing.
     *
     * @param  array $instances all instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances)
    {
        $now = time();
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > $now) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < $now) {
                continue;
            }
            return [new pix_icon('icon', get_string('pluginname', 'enrol_mpcheckoutpro'), 'enrol_mpcheckoutpro')];
        }
        return [];
    }

    /**
     * Users with the role assign capability may tweak roles later.
     *
     * @return bool
     */
    public function roles_protected()
    {
        return false;
    }

    /**
     * Users with the unenrol capability may unenrol other users manually.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance)
    {
        return true;
    }

    /**
     * Users with the manage capability may tweak period and status.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance)
    {
        return true;
    }

    /**
     * Show the "Enrol me" link when the instance is enabled.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance)
    {
        return (int)$instance->status === ENROL_INSTANCE_ENABLED;
    }

    /**
     * Whether an instance can be added to a course.
     *
     * @param  int $courseid
     * @return bool
     */
    public function can_add_instance($courseid)
    {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context)
            || !has_capability('enrol/mpcheckoutpro:config', $context)
        ) {
            return false;
        }
        // Multiple instances are supported: different price for different audiences.
        return true;
    }

    /**
     * Use the standard enrolment instance editing UI.
     *
     * @return bool
     */
    public function use_standard_editing_ui()
    {
        return true;
    }

    /**
     * Whether the instance can be deleted through the standard UI.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance)
    {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/mpcheckoutpro:config', $context);
    }

    /**
     * Whether the instance can be hidden or shown through the standard UI.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance)
    {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/mpcheckoutpro:config', $context);
    }

    /**
     * Add a new instance, normalising the money fields first.
     *
     * @param  object     $course
     * @param  array|null $fields
     * @return int|null
     */
    public function add_instance($course, ?array $fields = null)
    {
        if ($fields) {
            $fields = $this->normalise_fields($fields);
        }
        $instanceid = parent::add_instance($course, $fields);

        if ($instanceid && !empty($fields['mpaccesstoken'])) {
            credentials::store_for_instance(
                (int)$instanceid,
                (string)$fields['mpaccesstoken'],
                (string)($fields['mppublickey'] ?? ''),
                (string)($fields['mpwebhooksecret'] ?? ''),
            );
        }
        return $instanceid;
    }

    /**
     * Update an instance, normalising the money fields first.
     *
     * @param  stdClass $instance
     * @param  stdClass $data
     * @return bool
     */
    public function update_instance($instance, $data)
    {
        if ($data) {
            $fields = $this->normalise_fields((array)$data);
            foreach ($fields as $key => $value) {
                $data->$key = $value;
            }

            // Per instance credentials live outside the enrol table.
            if (property_exists($data, 'mpaccesstoken')) {
                $accesstoken = trim((string)$data->mpaccesstoken);
                $publickey = trim((string)($data->mppublickey ?? ''));
                $secret = trim((string)($data->mpwebhooksecret ?? ''));

                if ($accesstoken === '' && $publickey === '' && $secret === ''
                    && empty($data->mpkeepcredentials)
                ) {
                    credentials::delete_for_instance((int)$instance->id);
                } else if ($accesstoken !== '' || $publickey !== '' || $secret !== '') {
                    credentials::store_for_instance(
                        (int)$instance->id,
                        $accesstoken !== '' ? $accesstoken : null,
                        $publickey !== '' ? $publickey : null,
                        $secret !== '' ? $secret : null,
                    );
                }
            }
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Convert form values into what the enrol table expects.
     *
     * @param  array $fields
     * @return array
     */
    protected function normalise_fields(array $fields): array
    {
        if (isset($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        if (isset($fields['customdec1'])) {
            $fields['customdec1'] = unformat_float($fields['customdec1']);
        }

        // Fold the advanced Mercado Pago options into customtext2.
        // customtext1 is reserved for the course welcome message, as in enrol_self.
        $extra = [];
        if (isset($fields['mpexcludedtypes'])) {
            $extra['excludedpaymenttypes'] = array_values((array)$fields['mpexcludedtypes']);
        }
        if (isset($fields['mpexcludedmethods'])) {
            $extra['excludedpaymentmethods'] = array_values(
                array_filter(
                    array_map('trim', explode(',', (string)$fields['mpexcludedmethods']))
                )
            );
        }
        if (isset($fields['mpitemdescription'])) {
            $extra['itemdescription'] = trim((string)$fields['mpitemdescription']);
        }
        if (isset($fields['mpcategoryid'])) {
            $extra['categoryid'] = trim((string)$fields['mpcategoryid']);
        }
        if (isset($fields['mpnotifications'])) {
            $extra['notifications'] = (int)$fields['mpnotifications'];
        }
        if (isset($fields['mpmetadata'])) {
            $metadata = [];
            foreach (preg_split('/\R/', (string)$fields['mpmetadata']) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '=') === false) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                if (trim($key) !== '') {
                    $metadata[trim($key)] = trim($value);
                }
            }
            if ($metadata) {
                $extra['metadata'] = $metadata;
            }
        }
        if ($extra !== []) {
            $fields['customtext2'] = instance_settings::encode_extra($extra);
        }

        // Merge the two expiry notification settings back into their columns.
        if (isset($fields['expirynotify'])) {
            if ((int)$fields['expirynotify'] == 2) {
                $fields['expirynotify'] = 1;
                $fields['notifyall'] = 1;
            } else {
                $fields['notifyall'] = 0;
            }
        }

        return $fields;
    }

    /**
     * Remove the plugin owned data when an instance is deleted.
     *
     * @param  stdClass $instance
     * @return void
     */
    public function delete_instance($instance)
    {
        global $DB;

        // Transactions are kept for accounting but detached from the instance,
        // which is why courseid and userid are denormalised on the row.
        $DB->set_field(transaction::TABLE, 'enrolid', 0, ['enrolid' => $instance->id]);
        credentials::delete_for_instance((int)$instance->id);

        parent::delete_instance($instance);
    }

    /**
     * The enrolment page block shown to a user who is not enrolled yet.
     *
     * @param  stdClass $instance
     * @return string html
     */
    #[\Override]
    public function enrol_page_hook(stdClass $instance)
    {
        global $USER, $OUTPUT, $DB;

        $now = time();
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > $now) {
            return '';
        }
        if ($instance->enrolenddate != 0 && $instance->enrolenddate < $now) {
            return '';
        }

        $ue = $DB->get_record('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id]);
        if ($ue && (int)$ue->status === ENROL_USER_ACTIVE) {
            return '';
        }

        $course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        $settings = instance_settings::from_instance($instance);

        $name = !empty($instance->name)
            ? format_string($instance->name, true, ['context' => $context])
            : get_string('pluginname', 'enrol_mpcheckoutpro');

        // Anything that makes the purchase impossible is shown as a plain notice.
        $blocker = $this->get_blocking_notice($instance, $settings, $context);
        if ($blocker !== null) {
            return $OUTPUT->render(
                new enrol_page(
                    instance: $instance,
                    header: $name,
                    body: $OUTPUT->render($blocker),
                )
            );
        }

        $pending = $this->get_pending_transaction($instance, (int)$USER->id);

        $body = $OUTPUT->render_from_template(
            'enrol_mpcheckoutpro/enrol_page', [
            'cost' => $this->format_cost($settings),
            'currency' => $settings->currency,
            'description' => $settings->itemdescription !== ''
                ? format_text($settings->itemdescription, FORMAT_PLAIN, ['context' => $context])
                : '',
            'installments' => $settings->installments > 1 ? $settings->installments : 0,
            'testmode' => credentials::get_environment_setting() === credentials::ENV_TEST,
            'haspending' => $pending !== null,
            'pendingstatus' => $pending !== null ? status::label($pending->status) : '',
            ]
        );

        if (isguestuser() || !isloggedin()) {
            $button = new single_button(
                new moodle_url(get_login_url()),
                get_string('loginsite'),
                'get',
                single_button::BUTTON_PRIMARY
            );
        } else {
            $button = new single_button(
                util::plugin_url('checkout.php', ['instanceid' => $instance->id, 'sesskey' => sesskey()]),
                get_string('paybutton', 'enrol_mpcheckoutpro'),
                'post',
                single_button::BUTTON_PRIMARY
            );
        }

        return $OUTPUT->render(
            new enrol_page(
                instance: $instance,
                header: $name,
                body: $body,
                buttons: [$button],
            )
        );
    }

    /**
     * Build the notification explaining why the purchase cannot proceed, if any.
     *
     * @param  stdClass          $instance
     * @param  instance_settings $settings
     * @param  context           $context
     * @return \core\output\notification|null
     */
    protected function get_blocking_notice(stdClass $instance, instance_settings $settings, context $context)
    {
        $message = null;

        if ($settings->cost <= 0) {
            $message = get_string('error:nocost', 'enrol_mpcheckoutpro');
        } else if (!util::site_is_https()) {
            $message = get_string('error:httpsrequired', 'enrol_mpcheckoutpro');
        } else if (!sdk::is_available()) {
            $message = get_string('error:sdkmissing', 'enrol_mpcheckoutpro');
        } else if (!credentials::resolve($instance)->is_usable()) {
            $message = get_string('error:nocredentials', 'enrol_mpcheckoutpro');
        } else if (!\enrol_mpcheckoutpro\local\enrolment_manager::has_capacity($instance, $settings)) {
            $message = get_string('error:coursefull', 'enrol_mpcheckoutpro');
        }

        if ($message === null) {
            return null;
        }

        // Students should not be told about server side misconfiguration in detail.
        if (!has_capability('moodle/course:update', $context) && $settings->cost > 0) {
            $message = get_string('error:notavailable', 'enrol_mpcheckoutpro');
        }

        $notification = new \core\output\notification($message, \core\output\notification::NOTIFY_ERROR, false);
        $notification->set_extra_classes(['mb-0']);
        return $notification;
    }

    /**
     * The most recent transaction of a user that is still waiting to settle.
     *
     * @param  stdClass $instance
     * @param  int      $userid
     * @return stdClass|null
     */
    protected function get_pending_transaction(stdClass $instance, int $userid): ?stdClass
    {
        foreach (transaction::get_for_user((int)$instance->id, $userid) as $record) {
            if (in_array($record->status, [status::PENDING, status::IN_PROCESS, status::AUTHORIZED], true)) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Format the cost for display.
     *
     * @param  instance_settings $settings
     * @return string
     */
    protected function format_cost(instance_settings $settings): string
    {
        $locale = get_string('localecldr', 'langconfig');
        if (class_exists('\NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($settings->cost, $settings->currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        return $settings->currency . ' ' . number_format($settings->cost, 2);
    }

    /**
     * Add the transaction report to the enrolment method action icons.
     *
     * @param  stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance)
    {
        global $OUTPUT;

        $icons = parent::get_action_icons($instance);
        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/mpcheckoutpro:viewtransactions', $context)) {
            $url = util::plugin_url(
                'transactions.php', [
                'courseid' => $instance->courseid,
                'instanceid' => $instance->id,
                ]
            );
            $icons[] = $OUTPUT->action_icon(
                $url,
                new pix_icon(
                    'i/report', get_string('transactions', 'enrol_mpcheckoutpro'), 'core',
                    ['class' => 'iconsmall']
                )
            );
        }
        return $icons;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param  stdClass        $instance
     * @param  MoodleQuickForm $mform
     * @param  context         $context
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context)
    {

        // Merge the two expiry notification columns into one selector.
        if (!empty($instance->notifyall) && !empty($instance->expirynotify)) {
            $instance->expirynotify = 2;
        }
        unset($instance->notifyall);

        $settings = instance_settings::from_instance($instance);

        // ---------------------------------------------------------------- General.
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement(
            'text', 'customchar1', get_string('instancedescription', 'enrol_mpcheckoutpro'),
            ['size' => 40]
        );
        $mform->setType('customchar1', PARAM_TEXT);
        $mform->addHelpButton('customchar1', 'instancedescription', 'enrol_mpcheckoutpro');
        $mform->addRule('customchar1', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement(
            'select', 'status', get_string('status', 'enrol_mpcheckoutpro'),
            $this->get_status_options()
        );
        $mform->addHelpButton('status', 'status', 'enrol_mpcheckoutpro');
        $mform->setDefault('status', $this->get_config('status'));

        // ------------------------------------------------------------------ Price.
        $mform->addElement('text', 'cost', get_string('cost', 'enrol_mpcheckoutpro'), ['size' => 8]);
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float((float)$this->get_config('cost'), 2, true));
        $mform->addHelpButton('cost', 'cost', 'enrol_mpcheckoutpro');

        $mform->addElement(
            'select', 'currency', get_string('currency', 'enrol_mpcheckoutpro'),
            $this->get_possible_currencies()
        );
        $mform->setDefault('currency', $this->get_config('currency'));

        // ------------------------------------------------------------- Enrolment.
        $mform->addElement(
            'select', 'roleid', get_string('assignrole', 'enrol_mpcheckoutpro'),
            $this->get_roleid_options($instance, $context)
        );
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $groups = [0 => get_string('none')];
        foreach (groups_get_all_groups($context->instanceid) as $group) {
            $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
        }
        $mform->addElement('select', 'customint1', get_string('assigngroup', 'enrol_mpcheckoutpro'), $groups);
        $mform->addHelpButton('customint1', 'assigngroup', 'enrol_mpcheckoutpro');

        $mform->addElement(
            'duration', 'enrolperiod', get_string('enrolperiod', 'enrol_mpcheckoutpro'),
            ['optional' => true, 'defaultunit' => DAYSECS]
        );
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_mpcheckoutpro');

        $mform->addElement(
            'select', 'expirynotify', get_string('expirynotify', 'core_enrol'),
            $this->get_expirynotify_options()
        );
        $mform->addHelpButton('expirynotify', 'expirynotify', 'core_enrol');

        $mform->addElement(
            'duration', 'expirythreshold', get_string('expirythreshold', 'core_enrol'),
            ['optional' => false, 'defaultunit' => DAYSECS]
        );
        $mform->addHelpButton('expirythreshold', 'expirythreshold', 'core_enrol');
        $mform->disabledIf('expirythreshold', 'expirynotify', 'eq', 0);

        $mform->addElement(
            'date_time_selector', 'enrolstartdate',
            get_string('enrolstartdate', 'enrol_mpcheckoutpro'), ['optional' => true]
        );
        $mform->setDefault('enrolstartdate', 0);

        $mform->addElement(
            'date_time_selector', 'enrolenddate',
            get_string('enrolenddate', 'enrol_mpcheckoutpro'), ['optional' => true]
        );
        $mform->setDefault('enrolenddate', 0);

        $mform->addElement('text', 'customint5', get_string('maxenrolled', 'enrol_mpcheckoutpro'), ['size' => 6]);
        $mform->setType('customint5', PARAM_INT);
        $mform->addHelpButton('customint5', 'maxenrolled', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint5', 0);

        // ------------------------------------------------- Payment behaviour.
        $mform->addElement('header', 'mpbehaviour', get_string('paymentbehaviour', 'enrol_mpcheckoutpro'));

        $trilean = [
            -1 => get_string('usesitedefault', 'enrol_mpcheckoutpro'),
            0 => get_string('no'),
            1 => get_string('yes'),
        ];
        $mform->addElement('select', 'customint3', get_string('pendingholding', 'enrol_mpcheckoutpro'), $trilean);
        $mform->addHelpButton('customint3', 'pendingholding', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint3', -1);

        $mform->addElement(
            'select', 'mpnotifications', get_string('notifications', 'enrol_mpcheckoutpro'),
            $trilean
        );
        $mform->addHelpButton('mpnotifications', 'notifications', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpnotifications', $settings->notificationsraw);

        $reversal = [
            -1 => get_string('usesitedefault', 'enrol_mpcheckoutpro'),
            instance_settings::REVERSAL_KEEP => get_string('reversalkeep', 'enrol_mpcheckoutpro'),
            instance_settings::REVERSAL_SUSPEND => get_string('reversalsuspend', 'enrol_mpcheckoutpro'),
            instance_settings::REVERSAL_UNENROL => get_string('reversalunenrol', 'enrol_mpcheckoutpro'),
        ];
        $mform->addElement('select', 'customint6', get_string('reversalaction', 'enrol_mpcheckoutpro'), $reversal);
        $mform->addHelpButton('customint6', 'reversalaction', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint6', -1);

        // ------------------------------------------------- Course welcome message.
        // Same behaviour and the same enrol table columns as enrol_self, so the
        // message and its placeholders work exactly as course staff already expect.
        $mform->addElement(
            'select', 'customint4',
            get_string('sendcoursewelcomemessage', 'enrol_mpcheckoutpro'),
            $this->get_welcome_email_options()
        );
        $mform->addHelpButton('customint4', 'sendcoursewelcomemessage', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint4', $this->get_config('sendcoursewelcomemessage', ENROL_DO_NOT_SEND_EMAIL));

        $mform->addElement(
            'textarea', 'customtext1', get_string('customwelcomemessage', 'core_enrol'),
            ['cols' => '60', 'rows' => '8']
        );
        $mform->setType('customtext1', PARAM_RAW);
        $mform->setDefault('customtext1', get_string('customwelcomemessageplaceholder', 'core_enrol'));
        $mform->hideIf(
            elementname: 'customtext1',
            dependenton: 'customint4',
            condition: 'eq',
            value: ENROL_DO_NOT_SEND_EMAIL,
        );

        // Static elements cannot be hidden by hideIf(), so core wraps the help in a
        // dummy group. See MDL-66251.
        $welcomehelp = [];
        $welcomehelp[] = $mform->createElement(
            'static',
            'customwelcomemessage_extra_help',
            null,
            get_string(identifier: 'customwelcomemessage_help', component: 'core_enrol'),
        );
        $mform->addGroup($welcomehelp, 'group_customwelcomemessage_extra_help', '', ' ', false);
        $mform->hideIf(
            elementname: 'group_customwelcomemessage_extra_help',
            dependenton: 'customint4',
            condition: 'eq',
            value: ENROL_DO_NOT_SEND_EMAIL,
        );

        // ----------------------------------------- Checkout Pro advanced options.
        $mform->addElement('header', 'mpadvanced', get_string('advancedpreference', 'enrol_mpcheckoutpro'));

        $mform->addElement('text', 'customint2', get_string('installments', 'enrol_mpcheckoutpro'), ['size' => 4]);
        $mform->setType('customint2', PARAM_INT);
        $mform->addHelpButton('customint2', 'installments', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint2', 0);

        $mform->addElement(
            'text', 'customint7', get_string('defaultinstallments', 'enrol_mpcheckoutpro'),
            ['size' => 4]
        );
        $mform->setType('customint7', PARAM_INT);
        $mform->addHelpButton('customint7', 'defaultinstallments', 'enrol_mpcheckoutpro');
        $mform->setDefault('customint7', 0);

        $mform->addElement(
            'select', 'mpexcludedtypes', get_string('excludedpaymenttypes', 'enrol_mpcheckoutpro'),
            $this->get_payment_type_options(), ['multiple' => true, 'size' => 6]
        );
        $mform->addHelpButton('mpexcludedtypes', 'excludedpaymenttypes', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpexcludedtypes', $settings->excludedpaymenttypes);

        $mform->addElement(
            'text', 'mpexcludedmethods', get_string('excludedpaymentmethods', 'enrol_mpcheckoutpro'),
            ['size' => 40]
        );
        $mform->setType('mpexcludedmethods', PARAM_TEXT);
        $mform->addHelpButton('mpexcludedmethods', 'excludedpaymentmethods', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpexcludedmethods', implode(',', $settings->excludedpaymentmethods));

        $mform->addElement(
            'text', 'customchar2', get_string('defaultpaymentmethodid', 'enrol_mpcheckoutpro'),
            ['size' => 20]
        );
        $mform->setType('customchar2', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('customchar2', 'defaultpaymentmethodid', 'enrol_mpcheckoutpro');

        $mform->addElement(
            'textarea', 'mpitemdescription', get_string('itemdescription', 'enrol_mpcheckoutpro'),
            ['rows' => 3, 'cols' => 50]
        );
        $mform->setType('mpitemdescription', PARAM_TEXT);
        $mform->addHelpButton('mpitemdescription', 'itemdescription', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpitemdescription', $settings->itemdescription);

        $mform->addElement('text', 'mpcategoryid', get_string('categoryid', 'enrol_mpcheckoutpro'), ['size' => 20]);
        $mform->setType('mpcategoryid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('mpcategoryid', 'categoryid', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpcategoryid', $settings->categoryid);

        $metadatalines = [];
        foreach ($settings->custommetadata as $key => $value) {
            $metadatalines[] = $key . '=' . $value;
        }
        $mform->addElement(
            'textarea', 'mpmetadata', get_string('custommetadata', 'enrol_mpcheckoutpro'),
            ['rows' => 4, 'cols' => 50]
        );
        $mform->setType('mpmetadata', PARAM_TEXT);
        $mform->addHelpButton('mpmetadata', 'custommetadata', 'enrol_mpcheckoutpro');
        $mform->setDefault('mpmetadata', implode("\n", $metadatalines));

        // ------------------------------------------------------- Split payments.
        if ((bool)$this->get_config('marketplaceenabled')) {
            $mform->addElement('header', 'mpmarketplace', get_string('splitpayments', 'enrol_mpcheckoutpro'));

            $mform->addElement('advcheckbox', 'customint8', get_string('splitenabled', 'enrol_mpcheckoutpro'));
            $mform->addHelpButton('customint8', 'splitenabled', 'enrol_mpcheckoutpro');

            $mform->addElement(
                'text', 'customdec1', get_string('marketplacefee', 'enrol_mpcheckoutpro'),
                ['size' => 8]
            );
            $mform->setType('customdec1', PARAM_RAW);
            $mform->addHelpButton('customdec1', 'marketplacefee', 'enrol_mpcheckoutpro');
            $mform->disabledIf('customdec1', 'customint8', 'notchecked');

            $mform->addElement('text', 'customchar3', get_string('sellerid', 'enrol_mpcheckoutpro'), ['size' => 20]);
            $mform->setType('customchar3', PARAM_ALPHANUMEXT);
            $mform->addHelpButton('customchar3', 'sellerid', 'enrol_mpcheckoutpro');
            $mform->disabledIf('customchar3', 'customint8', 'notchecked');

            if (!empty($instance->id) && \enrol_mpcheckoutpro\local\oauth_helper::is_enabled()) {
                $connecturl = util::plugin_url(
                    'oauth.php', [
                    'action' => 'connect',
                    'instanceid' => $instance->id,
                    'sesskey' => sesskey(),
                    ]
                );
                $connected = credentials::instance_has_credentials((int)$instance->id);
                $mform->addElement(
                    'static', 'mpconnect', get_string('sellerconnection', 'enrol_mpcheckoutpro'),
                    html_writer::link(
                        $connecturl, get_string(
                            $connected ? 'reconnectseller' : 'connectseller',
                            'enrol_mpcheckoutpro'
                        )
                    )
                );
            }
        }

        // ---------------------------------------------- Per instance credentials.
        if (credentials::instance_override_allowed()) {
            $mform->addElement('header', 'mpcredentials', get_string('instancecredentials', 'enrol_mpcheckoutpro'));
            $mform->addElement(
                'static', 'mpcredentialsinfo', '',
                get_string('instancecredentials_desc', 'enrol_mpcheckoutpro')
            );

            $mform->addElement(
                'passwordunmask', 'mpaccesstoken',
                get_string('accesstoken', 'enrol_mpcheckoutpro')
            );
            $mform->setType('mpaccesstoken', PARAM_RAW_TRIMMED);

            $mform->addElement('passwordunmask', 'mppublickey', get_string('publickey', 'enrol_mpcheckoutpro'));
            $mform->setType('mppublickey', PARAM_RAW_TRIMMED);

            $mform->addElement(
                'passwordunmask', 'mpwebhooksecret',
                get_string('webhooksecret', 'enrol_mpcheckoutpro')
            );
            $mform->setType('mpwebhooksecret', PARAM_RAW_TRIMMED);

            if (!empty($instance->id) && credentials::instance_has_credentials((int)$instance->id)) {
                $mform->addElement(
                    'advcheckbox', 'mpkeepcredentials',
                    get_string('keepcredentials', 'enrol_mpcheckoutpro')
                );
                $mform->setDefault('mpkeepcredentials', 1);
                $mform->addHelpButton('mpkeepcredentials', 'keepcredentials', 'enrol_mpcheckoutpro');
            }
        }

        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement(
                'static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'),
                get_string('instanceeditselfwarningtext', 'core_enrol')
            );
        }
    }

    /**
     * Validate the instance edit form.
     *
     * @param  array   $data
     * @param  array   $files
     * @param  object  $instance
     * @param  context $context
     * @return array
     */
    public function edit_instance_validation($data, $files, $instance, $context)
    {
        $errors = [];

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', (string)($data['cost'] ?? ''));
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('error:costnotnumeric', 'enrol_mpcheckoutpro');
        } else if ((float)$cost <= 0 && (int)($data['status'] ?? 0) === ENROL_INSTANCE_ENABLED) {
            $errors['cost'] = get_string('error:costpositive', 'enrol_mpcheckoutpro');
        }

        if (!empty($data['enrolenddate']) && !empty($data['enrolstartdate'])
            && $data['enrolenddate'] < $data['enrolstartdate']
        ) {
            $errors['enrolenddate'] = get_string('error:enrolenddate', 'enrol_mpcheckoutpro');
        }

        if (!empty($data['expirynotify']) && $data['expirynotify'] > 0 && $data['expirythreshold'] < DAYSECS) {
            $errors['expirythreshold'] = get_string('errorthresholdlow', 'core_enrol');
        }

        $installments = (int)($data['customint2'] ?? 0);
        if ($installments < 0 || $installments > 36) {
            $errors['customint2'] = get_string('error:installmentsrange', 'enrol_mpcheckoutpro');
        }
        $definstallments = (int)($data['customint7'] ?? 0);
        if ($definstallments < 0 || ($installments > 0 && $definstallments > $installments)) {
            $errors['customint7'] = get_string('error:definstallmentsrange', 'enrol_mpcheckoutpro');
        }

        if (!empty($data['customint8'])) {
            $fee = str_replace(get_string('decsep', 'langconfig'), '.', (string)($data['customdec1'] ?? '0'));
            if (!is_numeric($fee) || (float)$fee < 0) {
                $errors['customdec1'] = get_string('error:feenotnumeric', 'enrol_mpcheckoutpro');
            } else if (is_numeric($cost) && (float)$fee >= (float)$cost) {
                $errors['customdec1'] = get_string('error:feetoolarge', 'enrol_mpcheckoutpro');
            }
        }

        if (!empty($data['mpexcludedmethods'])) {
            foreach (explode(',', (string)$data['mpexcludedmethods']) as $id) {
                $id = trim($id);
                if ($id !== '' && !preg_match('/^[a-z0-9_]{1,32}$/', $id)) {
                    $errors['mpexcludedmethods'] = get_string('error:invalidmethodid', 'enrol_mpcheckoutpro');
                    break;
                }
            }
        }

        // ENROL_INSTANCE_ENABLED is 0, so this must be an isset() check: !empty()
        // would make the whole guard unreachable for an enabled instance.
        if (isset($data['status']) && (int)$data['status'] === ENROL_INSTANCE_ENABLED) {
            $probe = (object)['id' => $instance->id ?? 0];
            if (!credentials::resolve($probe)->is_usable()) {
                $errors['status'] = get_string('error:nocredentials', 'enrol_mpcheckoutpro');
            }
            if (!util::site_is_https()) {
                $errors['status'] = get_string('error:httpsrequired', 'enrol_mpcheckoutpro');
            }
        }

        $validstatus = array_keys($this->get_status_options());
        $validcurrency = array_keys($this->get_possible_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = [
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
            'customint1' => PARAM_INT,
            'customint5' => PARAM_INT,
            'customint4' => array_keys($this->get_welcome_email_options()),
        ];
        $errors = array_merge($errors, $this->validate_param_types($data, $tovalidate));

        unset($files);
        return $errors;
    }

    /**
     * "Send welcome email from" options for this plugin.
     *
     * Core's enrol_send_welcome_email_options() also offers "from the key holder",
     * which resolves the sender through the enrol/self:holdkey capability. There is
     * no key holder in a paid enrolment, so that option would silently send nothing
     * and it is left out.
     *
     * @return array
     */
    protected function get_welcome_email_options(): array
    {
        $options = enrol_send_welcome_email_options();
        unset($options[ENROL_SEND_EMAIL_FROM_KEY_HOLDER]);
        return $options;
    }

    /**
     * Default field values for a newly created instance.
     *
     * @return array
     */
    public function get_instance_defaults()
    {
        return [
            'status' => $this->get_config('status', ENROL_INSTANCE_DISABLED),
            'roleid' => $this->get_config('roleid'),
            'enrolperiod' => $this->get_config('enrolperiod', 0),
            'cost' => $this->get_config('cost', 0),
            'currency' => $this->get_config('currency', 'ARS'),
            'customint4' => $this->get_config('sendcoursewelcomemessage', ENROL_DO_NOT_SEND_EMAIL),
            'expirynotify' => 0,
            'expirythreshold' => 0,
        ];
    }

    /**
     * Options for the instance status selector.
     *
     * @return array
     */
    protected function get_status_options()
    {
        return [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
    }

    /**
     * Options for the role selector.
     *
     * @param  object  $instance
     * @param  context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context)
    {
        if (!empty($instance->id)) {
            return get_default_enrol_roles($context, $instance->roleid);
        }
        return get_default_enrol_roles($context, $this->get_config('roleid'));
    }

    /**
     * Options for the expiry notification selector.
     *
     * @return array
     */
    protected function get_expirynotify_options()
    {
        return [
            0 => get_string('no'),
            1 => get_string('expirynotifyenroller', 'core_enrol'),
            2 => get_string('expirynotifyall', 'core_enrol'),
        ];
    }

    /**
     * The payment types that Checkout Pro can be told to exclude.
     *
     * The authoritative list for a given account comes from GET /v1/payment_methods;
     * these are the ids the Checkout Pro documentation uses in its examples and are
     * offered here as a convenience. Anything else can be entered by id.
     *
     * @return array
     */
    protected function get_payment_type_options(): array
    {
        return [
            'credit_card' => get_string('paymenttype_credit_card', 'enrol_mpcheckoutpro'),
            'debit_card' => get_string('paymenttype_debit_card', 'enrol_mpcheckoutpro'),
            'prepaid_card' => get_string('paymenttype_prepaid_card', 'enrol_mpcheckoutpro'),
            'ticket' => get_string('paymenttype_ticket', 'enrol_mpcheckoutpro'),
            'bank_transfer' => get_string('paymenttype_bank_transfer', 'enrol_mpcheckoutpro'),
            'atm' => get_string('paymenttype_atm', 'enrol_mpcheckoutpro'),
        ];
    }

    /**
     * Restore an instance during a course restore.
     *
     * Credentials are deliberately not restored: they never leave the site they
     * were entered on.
     *
     * @param  restore_enrolments_structure_step $step
     * @param  stdClass                          $data
     * @param  stdClass                          $course
     * @param  int                               $oldid
     * @return void
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid)
    {
        global $DB;

        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = [
                'courseid' => $data->courseid,
                'enrol' => $this->get_name(),
                'roleid' => $data->roleid,
                'cost' => $data->cost,
                'currency' => $data->currency,
            ];
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            // A restored instance starts disabled: the credentials must be checked first.
            $data->status = ENROL_INSTANCE_DISABLED;
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore a user enrolment.
     *
     * @param  restore_enrolments_structure_step $step
     * @param  stdClass                          $data
     * @param  stdClass                          $instance
     * @param  int                               $userid
     * @param  int                               $oldinstancestatus
     * @return void
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid,
        $oldinstancestatus
    ) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
        unset($step, $oldinstancestatus);
    }

    /**
     * Restore group membership.
     *
     * @param  stdClass $instance
     * @param  int      $groupid
     * @param  int      $userid
     * @return void
     */
    public function restore_group_member($instance, $groupid, $userid)
    {
        global $CFG;
        include_once $CFG->dirroot . '/group/lib.php';
        groups_add_member($groupid, $userid);
        unset($instance);
    }

    /**
     * Scheduled synchronisation: expire enrolments that ran out.
     *
     * @param  progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace)
    {
        $this->process_expirations($trace);
        return 0;
    }

    /**
     * Let the participants page offer a link to the payment behind an enrolment.
     *
     * @param  course_enrolment_manager $manager
     * @param  stdClass                 $ue
     * @return array
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue)
    {
        $actions = parent::get_user_enrolment_actions($manager, $ue);
        $context = $manager->get_context();

        if (has_capability('enrol/mpcheckoutpro:viewtransactions', $context)) {
            $url = util::plugin_url(
                'transactions.php', [
                'courseid' => $context->instanceid,
                'userid' => $ue->userid,
                ]
            );
            $actions[] = new user_enrolment_action(
                new pix_icon('i/report', get_string('transactions', 'enrol_mpcheckoutpro')),
                get_string('transactions', 'enrol_mpcheckoutpro'),
                $url,
                ['class' => 'viewmptransactions']
            );
        }
        return $actions;
    }
}
