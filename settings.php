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
 * Site settings for the Mercado Pago Checkout Pro enrolment plugin.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_mpcheckoutpro\local\credentials;
use enrol_mpcheckoutpro\local\instance_settings;
use enrol_mpcheckoutpro\local\oauth_helper;
use enrol_mpcheckoutpro\local\sdk;
use enrol_mpcheckoutpro\local\util;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // ------------------------------------------------------------- Diagnostics.
    $notices = [];
    if (!sdk::is_available()) {
        $notices[] = new \core\output\notification(
            get_string('error:sdkmissing', 'enrol_mpcheckoutpro'),
            \core\output\notification::NOTIFY_ERROR
        );
    } else {
        $notices[] = new \core\output\notification(
            get_string('sdkversion', 'enrol_mpcheckoutpro', sdk::get_version()),
            \core\output\notification::NOTIFY_INFO
        );
    }
    if (!util::site_is_https()) {
        $notices[] = new \core\output\notification(
            get_string('error:httpsrequired', 'enrol_mpcheckoutpro'),
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $notices[] = new \core\output\notification(
        get_string('webhookurl_desc', 'enrol_mpcheckoutpro',
            util::plugin_url('webhook.php')->out(false)),
        \core\output\notification::NOTIFY_INFO
    );

    $noticehtml = '';
    foreach ($notices as $notice) {
        $noticehtml .= $OUTPUT->render($notice);
    }
    $settings->add(new admin_setting_heading('enrol_mpcheckoutpro_status', '', $noticehtml));

    // ------------------------------------------------------------ Credentials.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_credentials',
        get_string('settings_credentials', 'enrol_mpcheckoutpro'),
        get_string('settings_credentials_desc', 'enrol_mpcheckoutpro')
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/environment',
        get_string('environment', 'enrol_mpcheckoutpro'),
        get_string('environment_desc', 'enrol_mpcheckoutpro'),
        credentials::ENV_PRODUCTION,
        [
            credentials::ENV_PRODUCTION => get_string('environment_production', 'enrol_mpcheckoutpro'),
            credentials::ENV_TEST => get_string('environment_test', 'enrol_mpcheckoutpro'),
        ]
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/accesstoken',
        get_string('accesstoken', 'enrol_mpcheckoutpro'),
        get_string('accesstoken_desc', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/publickey',
        get_string('publickey', 'enrol_mpcheckoutpro'),
        get_string('publickey_desc', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/webhooksecret',
        get_string('webhooksecret', 'enrol_mpcheckoutpro'),
        get_string('webhooksecret_desc', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/testaccesstoken',
        get_string('testaccesstoken', 'enrol_mpcheckoutpro'),
        get_string('testaccesstoken_desc', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/testpublickey',
        get_string('testpublickey', 'enrol_mpcheckoutpro'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/testwebhooksecret',
        get_string('testwebhooksecret', 'enrol_mpcheckoutpro'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/allowinstancecredentials',
        get_string('allowinstancecredentials', 'enrol_mpcheckoutpro'),
        get_string('allowinstancecredentials_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    // --------------------------------------------------------------- Webhooks.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_webhooks',
        get_string('settings_webhooks', 'enrol_mpcheckoutpro'),
        get_string('settings_webhooks_desc', 'enrol_mpcheckoutpro')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/requiresignature',
        get_string('requiresignature', 'enrol_mpcheckoutpro'),
        get_string('requiresignature_desc', 'enrol_mpcheckoutpro'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/signaturetolerance',
        get_string('signaturetolerance', 'enrol_mpcheckoutpro'),
        get_string('signaturetolerance_desc', 'enrol_mpcheckoutpro'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/deferwebhooks',
        get_string('deferwebhooks', 'enrol_mpcheckoutpro'),
        get_string('deferwebhooks_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/webhookratelimit',
        get_string('webhookratelimit', 'enrol_mpcheckoutpro'),
        get_string('webhookratelimit_desc', 'enrol_mpcheckoutpro'),
        120,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/checkoutratelimit',
        get_string('checkoutratelimit', 'enrol_mpcheckoutpro'),
        get_string('checkoutratelimit_desc', 'enrol_mpcheckoutpro'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/reconcilemaxattempts',
        get_string('reconcilemaxattempts', 'enrol_mpcheckoutpro'),
        get_string('reconcilemaxattempts_desc', 'enrol_mpcheckoutpro'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configduration(
        'enrol_mpcheckoutpro/reconcilemaxage',
        get_string('reconcilemaxage', 'enrol_mpcheckoutpro'),
        get_string('reconcilemaxage_desc', 'enrol_mpcheckoutpro'),
        90 * DAYSECS
    ));

    // ------------------------------------------------- Checkout Pro preference.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_preference',
        get_string('settings_preference', 'enrol_mpcheckoutpro'),
        get_string('settings_preference_desc', 'enrol_mpcheckoutpro')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/autoreturn',
        get_string('autoreturn', 'enrol_mpcheckoutpro'),
        get_string('autoreturn_desc', 'enrol_mpcheckoutpro'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/binarymode',
        get_string('binarymode', 'enrol_mpcheckoutpro'),
        get_string('binarymode_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/statementdescriptor',
        get_string('statementdescriptor', 'enrol_mpcheckoutpro'),
        get_string('statementdescriptor_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_TEXT,
        24
    ));

    $settings->add(new admin_setting_configduration(
        'enrol_mpcheckoutpro/preferenceexpiry',
        get_string('preferenceexpiry', 'enrol_mpcheckoutpro'),
        get_string('preferenceexpiry_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/installments',
        get_string('installments', 'enrol_mpcheckoutpro'),
        get_string('installments_desc', 'enrol_mpcheckoutpro'),
        0,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/defaultinstallments',
        get_string('defaultinstallments', 'enrol_mpcheckoutpro'),
        get_string('defaultinstallments_desc', 'enrol_mpcheckoutpro'),
        0,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'enrol_mpcheckoutpro/excludedpaymenttypes',
        get_string('excludedpaymenttypes', 'enrol_mpcheckoutpro'),
        get_string('excludedpaymenttypes_desc', 'enrol_mpcheckoutpro'),
        [],
        [
            'credit_card' => get_string('paymenttype_credit_card', 'enrol_mpcheckoutpro'),
            'debit_card' => get_string('paymenttype_debit_card', 'enrol_mpcheckoutpro'),
            'prepaid_card' => get_string('paymenttype_prepaid_card', 'enrol_mpcheckoutpro'),
            'ticket' => get_string('paymenttype_ticket', 'enrol_mpcheckoutpro'),
            'bank_transfer' => get_string('paymenttype_bank_transfer', 'enrol_mpcheckoutpro'),
            'atm' => get_string('paymenttype_atm', 'enrol_mpcheckoutpro'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/excludedpaymentmethods',
        get_string('excludedpaymentmethods', 'enrol_mpcheckoutpro'),
        get_string('excludedpaymentmethods_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/defaultpaymentmethodid',
        get_string('defaultpaymentmethodid', 'enrol_mpcheckoutpro'),
        get_string('defaultpaymentmethodid_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'enrol_mpcheckoutpro/custommetadata',
        get_string('custommetadata', 'enrol_mpcheckoutpro'),
        get_string('custommetadata_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_TEXT
    ));

    // -------------------------------------------------------- Split payments.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_marketplace',
        get_string('settings_marketplace', 'enrol_mpcheckoutpro'),
        get_string('settings_marketplace_desc', 'enrol_mpcheckoutpro', [
            'redirecturi' => oauth_helper::get_redirect_uri()->out(false),
        ])
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/marketplaceenabled',
        get_string('marketplaceenabled', 'enrol_mpcheckoutpro'),
        get_string('marketplaceenabled_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/marketplaceclientid',
        get_string('marketplaceclientid', 'enrol_mpcheckoutpro'),
        get_string('marketplaceclientid_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_mpcheckoutpro/marketplaceclientsecret',
        get_string('marketplaceclientsecret', 'enrol_mpcheckoutpro'),
        get_string('marketplaceclientsecret_desc', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/marketplacename',
        get_string('marketplacename', 'enrol_mpcheckoutpro'),
        get_string('marketplacename_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_TEXT
    ));

    // ---------------------------------------------------- Enrolment behaviour.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_behaviour',
        get_string('settings_behaviour', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/pendingholding',
        get_string('pendingholding', 'enrol_mpcheckoutpro'),
        get_string('pendingholding_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/reversalaction',
        get_string('reversalaction', 'enrol_mpcheckoutpro'),
        get_string('reversalaction_desc', 'enrol_mpcheckoutpro'),
        instance_settings::REVERSAL_SUSPEND,
        [
            instance_settings::REVERSAL_KEEP => get_string('reversalkeep', 'enrol_mpcheckoutpro'),
            instance_settings::REVERSAL_SUSPEND => get_string('reversalsuspend', 'enrol_mpcheckoutpro'),
            instance_settings::REVERSAL_UNENROL => get_string('reversalunenrol', 'enrol_mpcheckoutpro'),
        ]
    ));

    $welcomeoptions = enrol_send_welcome_email_options();
    unset($welcomeoptions[ENROL_SEND_EMAIL_FROM_KEY_HOLDER]);
    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/sendcoursewelcomemessage',
        get_string('sendcoursewelcomemessage', 'enrol_mpcheckoutpro'),
        get_string('sendcoursewelcomemessage_desc', 'enrol_mpcheckoutpro'),
        ENROL_DO_NOT_SEND_EMAIL,
        $welcomeoptions
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/notifications',
        get_string('notifications', 'enrol_mpcheckoutpro'),
        get_string('notifications_desc', 'enrol_mpcheckoutpro'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/expiredaction',
        get_string('expiredaction', 'enrol_mpcheckoutpro'),
        get_string('expiredaction_desc', 'enrol_mpcheckoutpro'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES,
        [
            ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
        ]
    ));

    $settings->add(new admin_setting_configduration(
        'enrol_mpcheckoutpro/cleanupafter',
        get_string('cleanupafter', 'enrol_mpcheckoutpro'),
        get_string('cleanupafter_desc', 'enrol_mpcheckoutpro'),
        180 * DAYSECS
    ));

    // ------------------------------------------------------------ Diagnostics.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_diagnostics',
        get_string('settings_diagnostics', 'enrol_mpcheckoutpro'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_mpcheckoutpro/debuglogging',
        get_string('debuglogging', 'enrol_mpcheckoutpro'),
        get_string('debuglogging_desc', 'enrol_mpcheckoutpro'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/apitimeout',
        get_string('apitimeout', 'enrol_mpcheckoutpro'),
        get_string('apitimeout_desc', 'enrol_mpcheckoutpro'),
        10,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/apimaxretries',
        get_string('apimaxretries', 'enrol_mpcheckoutpro'),
        get_string('apimaxretries_desc', 'enrol_mpcheckoutpro'),
        3,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/integratorid',
        get_string('integratorid', 'enrol_mpcheckoutpro'),
        get_string('integratorid_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/platformid',
        get_string('platformid', 'enrol_mpcheckoutpro'),
        get_string('platformid_desc', 'enrol_mpcheckoutpro'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // --------------------------------------------------- Instance defaults.
    $settings->add(new admin_setting_heading(
        'enrol_mpcheckoutpro_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/status',
        get_string('status', 'enrol_mpcheckoutpro'),
        get_string('status_desc', 'enrol_mpcheckoutpro'),
        ENROL_INSTANCE_DISABLED,
        [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_mpcheckoutpro/cost',
        get_string('cost', 'enrol_mpcheckoutpro'),
        '',
        0,
        PARAM_FLOAT,
        6
    ));

    $currencies = [];
    foreach (util::supported_currencies() as $code) {
        $currencies[$code] = new lang_string($code, 'core_currencies');
    }
    $settings->add(new admin_setting_configselect(
        'enrol_mpcheckoutpro/currency',
        get_string('currency', 'enrol_mpcheckoutpro'),
        '',
        'ARS',
        $currencies
    ));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_mpcheckoutpro/roleid',
            get_string('defaultrole', 'enrol_mpcheckoutpro'),
            get_string('defaultrole_desc', 'enrol_mpcheckoutpro'),
            $student->id ?? null,
            $options
        ));
    }

    $settings->add(new admin_setting_configduration(
        'enrol_mpcheckoutpro/enrolperiod',
        get_string('enrolperiod', 'enrol_mpcheckoutpro'),
        get_string('enrolperiod_desc', 'enrol_mpcheckoutpro'),
        0
    ));
}
