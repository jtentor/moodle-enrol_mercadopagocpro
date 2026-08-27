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
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_mercadopagocpro\local\credentials;
use enrol_mercadopagocpro\local\instance_settings;
use enrol_mercadopagocpro\local\oauth_helper;
use enrol_mercadopagocpro\local\sdk;
use enrol_mercadopagocpro\local\util;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Diagnostics.
    $notices = [];
    if (!sdk::is_available()) {
        $notices[] = new \core\output\notification(
            get_string('error:sdkmissing', 'enrol_mercadopagocpro'),
            \core\output\notification::NOTIFY_ERROR
        );
    } else {
        $notices[] = new \core\output\notification(
            get_string('sdkversion', 'enrol_mercadopagocpro', sdk::get_version()),
            \core\output\notification::NOTIFY_INFO
        );
    }
    if (!util::site_is_https()) {
        $notices[] = new \core\output\notification(
            get_string('error:httpsrequired', 'enrol_mercadopagocpro'),
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $notices[] = new \core\output\notification(
        get_string(
            'webhookurl_desc', 'enrol_mercadopagocpro',
            util::plugin_url('webhook.php')->out(false)
        ),
        \core\output\notification::NOTIFY_INFO
    );

    $noticehtml = '';
    foreach ($notices as $notice) {
        $noticehtml .= $OUTPUT->render($notice);
    }
    $settings->add(new admin_setting_heading('enrol_mercadopagocpro_status', '', $noticehtml));

    // Credentials.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_credentials',
            get_string('settings_credentials', 'enrol_mercadopagocpro'),
            get_string('settings_credentials_desc', 'enrol_mercadopagocpro')
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/environment',
            get_string('environment', 'enrol_mercadopagocpro'),
            get_string('environment_desc', 'enrol_mercadopagocpro'),
            credentials::ENV_PRODUCTION,
            [
            credentials::ENV_PRODUCTION => get_string('environment_production', 'enrol_mercadopagocpro'),
            credentials::ENV_TEST => get_string('environment_test', 'enrol_mercadopagocpro'),
            ]
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/accesstoken',
            get_string('accesstoken', 'enrol_mercadopagocpro'),
            get_string('accesstoken_desc', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/publickey',
            get_string('publickey', 'enrol_mercadopagocpro'),
            get_string('publickey_desc', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/webhooksecret',
            get_string('webhooksecret', 'enrol_mercadopagocpro'),
            get_string('webhooksecret_desc', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/testaccesstoken',
            get_string('testaccesstoken', 'enrol_mercadopagocpro'),
            get_string('testaccesstoken_desc', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/testpublickey',
            get_string('testpublickey', 'enrol_mercadopagocpro'),
            '',
            ''
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/testwebhooksecret',
            get_string('testwebhooksecret', 'enrol_mercadopagocpro'),
            '',
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/allowinstancecredentials',
            get_string('allowinstancecredentials', 'enrol_mercadopagocpro'),
            get_string('allowinstancecredentials_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    // Webhooks.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_webhooks',
            get_string('settings_webhooks', 'enrol_mercadopagocpro'),
            get_string('settings_webhooks_desc', 'enrol_mercadopagocpro')
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/requiresignature',
            get_string('requiresignature', 'enrol_mercadopagocpro'),
            get_string('requiresignature_desc', 'enrol_mercadopagocpro'),
            1
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/signaturetolerance',
            get_string('signaturetolerance', 'enrol_mercadopagocpro'),
            get_string('signaturetolerance_desc', 'enrol_mercadopagocpro'),
            0,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/deferwebhooks',
            get_string('deferwebhooks', 'enrol_mercadopagocpro'),
            get_string('deferwebhooks_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/webhookratelimit',
            get_string('webhookratelimit', 'enrol_mercadopagocpro'),
            get_string('webhookratelimit_desc', 'enrol_mercadopagocpro'),
            120,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/checkoutratelimit',
            get_string('checkoutratelimit', 'enrol_mercadopagocpro'),
            get_string('checkoutratelimit_desc', 'enrol_mercadopagocpro'),
            10,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/reconcilemaxattempts',
            get_string('reconcilemaxattempts', 'enrol_mercadopagocpro'),
            get_string('reconcilemaxattempts_desc', 'enrol_mercadopagocpro'),
            60,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configduration(
            'enrol_mercadopagocpro/reconcilemaxage',
            get_string('reconcilemaxage', 'enrol_mercadopagocpro'),
            get_string('reconcilemaxage_desc', 'enrol_mercadopagocpro'),
            90 * DAYSECS
        )
    );

    // Checkout Pro preference.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_preference',
            get_string('settings_preference', 'enrol_mercadopagocpro'),
            get_string('settings_preference_desc', 'enrol_mercadopagocpro')
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/autoreturn',
            get_string('autoreturn', 'enrol_mercadopagocpro'),
            get_string('autoreturn_desc', 'enrol_mercadopagocpro'),
            1
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/binarymode',
            get_string('binarymode', 'enrol_mercadopagocpro'),
            get_string('binarymode_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/walletpurchase',
            get_string('walletpurchase', 'enrol_mercadopagocpro'),
            get_string('walletpurchase_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/statementdescriptor',
            get_string('statementdescriptor', 'enrol_mercadopagocpro'),
            get_string('statementdescriptor_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_TEXT,
            24
        )
    );

    $settings->add(
        new admin_setting_configduration(
            'enrol_mercadopagocpro/preferenceexpiry',
            get_string('preferenceexpiry', 'enrol_mercadopagocpro'),
            get_string('preferenceexpiry_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/installments',
            get_string('installments', 'enrol_mercadopagocpro'),
            get_string('installments_desc', 'enrol_mercadopagocpro'),
            0,
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/defaultinstallments',
            get_string('defaultinstallments', 'enrol_mercadopagocpro'),
            get_string('defaultinstallments_desc', 'enrol_mercadopagocpro'),
            0,
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configmulticheckbox(
            'enrol_mercadopagocpro/excludedpaymenttypes',
            get_string('excludedpaymenttypes', 'enrol_mercadopagocpro'),
            get_string('excludedpaymenttypes_desc', 'enrol_mercadopagocpro'),
            [],
            [
            'credit_card' => get_string('paymenttype_credit_card', 'enrol_mercadopagocpro'),
            'debit_card' => get_string('paymenttype_debit_card', 'enrol_mercadopagocpro'),
            'prepaid_card' => get_string('paymenttype_prepaid_card', 'enrol_mercadopagocpro'),
            'ticket' => get_string('paymenttype_ticket', 'enrol_mercadopagocpro'),
            'bank_transfer' => get_string('paymenttype_bank_transfer', 'enrol_mercadopagocpro'),
            'atm' => get_string('paymenttype_atm', 'enrol_mercadopagocpro'),
            ]
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/excludedpaymentmethods',
            get_string('excludedpaymentmethods', 'enrol_mercadopagocpro'),
            get_string('excludedpaymentmethods_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_TEXT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/defaultpaymentmethodid',
            get_string('defaultpaymentmethodid', 'enrol_mercadopagocpro'),
            get_string('defaultpaymentmethodid_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_ALPHANUMEXT
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'enrol_mercadopagocpro/custommetadata',
            get_string('custommetadata', 'enrol_mercadopagocpro'),
            get_string('custommetadata_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_TEXT
        )
    );

    // Split payments.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_marketplace',
            get_string('settings_marketplace', 'enrol_mercadopagocpro'),
            get_string(
                'settings_marketplace_desc', 'enrol_mercadopagocpro', [
                'redirecturi' => oauth_helper::get_redirect_uri()->out(false),
                ]
            )
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/marketplaceenabled',
            get_string('marketplaceenabled', 'enrol_mercadopagocpro'),
            get_string('marketplaceenabled_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/marketplaceclientid',
            get_string('marketplaceclientid', 'enrol_mercadopagocpro'),
            get_string('marketplaceclientid_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_ALPHANUMEXT
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'enrol_mercadopagocpro/marketplaceclientsecret',
            get_string('marketplaceclientsecret', 'enrol_mercadopagocpro'),
            get_string('marketplaceclientsecret_desc', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/marketplacename',
            get_string('marketplacename', 'enrol_mercadopagocpro'),
            get_string('marketplacename_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_TEXT
        )
    );

    // Enrolment behaviour.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_behaviour',
            get_string('settings_behaviour', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/pendingholding',
            get_string('pendingholding', 'enrol_mercadopagocpro'),
            get_string('pendingholding_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/reversalaction',
            get_string('reversalaction', 'enrol_mercadopagocpro'),
            get_string('reversalaction_desc', 'enrol_mercadopagocpro'),
            instance_settings::REVERSAL_SUSPEND,
            [
            instance_settings::REVERSAL_KEEP => get_string('reversalkeep', 'enrol_mercadopagocpro'),
            instance_settings::REVERSAL_SUSPEND => get_string('reversalsuspend', 'enrol_mercadopagocpro'),
            instance_settings::REVERSAL_UNENROL => get_string('reversalunenrol', 'enrol_mercadopagocpro'),
            ]
        )
    );

    $welcomeoptions = enrol_send_welcome_email_options();
    unset($welcomeoptions[ENROL_SEND_EMAIL_FROM_KEY_HOLDER]);
    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/sendcoursewelcomemessage',
            get_string('sendcoursewelcomemessage', 'enrol_mercadopagocpro'),
            get_string('sendcoursewelcomemessage_desc', 'enrol_mercadopagocpro'),
            ENROL_DO_NOT_SEND_EMAIL,
            $welcomeoptions
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/notifications',
            get_string('notifications', 'enrol_mercadopagocpro'),
            get_string('notifications_desc', 'enrol_mercadopagocpro'),
            1
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/expiredaction',
            get_string('expiredaction', 'enrol_mercadopagocpro'),
            get_string('expiredaction_desc', 'enrol_mercadopagocpro'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES,
            [
            ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
            ]
        )
    );

    $settings->add(
        new admin_setting_configduration(
            'enrol_mercadopagocpro/cleanupafter',
            get_string('cleanupafter', 'enrol_mercadopagocpro'),
            get_string('cleanupafter_desc', 'enrol_mercadopagocpro'),
            180 * DAYSECS
        )
    );

    // Diagnostics.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_diagnostics',
            get_string('settings_diagnostics', 'enrol_mercadopagocpro'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'enrol_mercadopagocpro/debuglogging',
            get_string('debuglogging', 'enrol_mercadopagocpro'),
            get_string('debuglogging_desc', 'enrol_mercadopagocpro'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/apitimeout',
            get_string('apitimeout', 'enrol_mercadopagocpro'),
            get_string('apitimeout_desc', 'enrol_mercadopagocpro'),
            10,
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/apimaxretries',
            get_string('apimaxretries', 'enrol_mercadopagocpro'),
            get_string('apimaxretries_desc', 'enrol_mercadopagocpro'),
            3,
            PARAM_INT,
            4
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/integratorid',
            get_string('integratorid', 'enrol_mercadopagocpro'),
            get_string('integratorid_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_ALPHANUMEXT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/platformid',
            get_string('platformid', 'enrol_mercadopagocpro'),
            get_string('platformid_desc', 'enrol_mercadopagocpro'),
            '',
            PARAM_ALPHANUMEXT
        )
    );

    // Instance defaults.
    $settings->add(
        new admin_setting_heading(
            'enrol_mercadopagocpro_defaults',
            get_string('enrolinstancedefaults', 'admin'),
            get_string('enrolinstancedefaults_desc', 'admin')
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/status',
            get_string('status', 'enrol_mercadopagocpro'),
            get_string('status_desc', 'enrol_mercadopagocpro'),
            ENROL_INSTANCE_DISABLED,
            [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
            ]
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'enrol_mercadopagocpro/cost',
            get_string('cost', 'enrol_mercadopagocpro'),
            '',
            0,
            PARAM_FLOAT,
            6
        )
    );

    $currencies = [];
    foreach (util::supported_currencies() as $code) {
        $currencies[$code] = new lang_string($code, 'core_currencies');
    }
    $settings->add(
        new admin_setting_configselect(
            'enrol_mercadopagocpro/currency',
            get_string('currency', 'enrol_mercadopagocpro'),
            '',
            'ARS',
            $currencies
        )
    );

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(
            new admin_setting_configselect(
                'enrol_mercadopagocpro/roleid',
                get_string('defaultrole', 'enrol_mercadopagocpro'),
                get_string('defaultrole_desc', 'enrol_mercadopagocpro'),
                $student->id ?? null,
                $options
            )
        );
    }

    $settings->add(
        new admin_setting_configduration(
            'enrol_mercadopagocpro/enrolperiod',
            get_string('enrolperiod', 'enrol_mercadopagocpro'),
            get_string('enrolperiod_desc', 'enrol_mercadopagocpro'),
            0
        )
    );
}
