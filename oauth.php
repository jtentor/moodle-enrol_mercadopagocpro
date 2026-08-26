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
 * Split payments: connects a course to a Mercado Pago seller through OAuth.
 *
 * Acts both as the entry point ("connect") and as the redirect_uri registered in
 * the Mercado Pago application.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://www.mercadopago.com.br/developers/en/docs/security/oauth/creation
 */

require __DIR__ . '/../../config.php';

use enrol_mercadopagocpro\local\api_exception;
use enrol_mercadopagocpro\local\oauth_helper;
use enrol_mercadopagocpro\local\util;

$action = optional_param('action', '', PARAM_ALPHA);
$code = optional_param('code', '', PARAM_RAW_TRIMMED);
$state = optional_param('state', '', PARAM_RAW_TRIMMED);
$error = optional_param('error', '', PARAM_TEXT);

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(util::plugin_url('oauth.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('splitpayments', 'enrol_mercadopagocpro'));
$PAGE->set_heading(get_string('splitpayments', 'enrol_mercadopagocpro'));

if (!oauth_helper::is_enabled()) {
    throw new moodle_exception('error:marketplacedisabled', 'enrol_mercadopagocpro');
}

// ---------------------------------------------------------------- Start flow.
if ($action === 'connect') {
    require_sesskey();
    $instanceid = required_param('instanceid', PARAM_INT);

    $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopagocpro'], '*', MUST_EXIST);
    $context = context_course::instance($instance->courseid, MUST_EXIST);
    require_capability('enrol/mercadopagocpro:config', $context);

    redirect(new moodle_url(oauth_helper::build_authorization_url($instanceid)));
}

// ----------------------------------------------------------- Handle callback.
$returnurl = new moodle_url('/enrol/instances.php');

if ($error !== '') {
    util::log_error('Mercado Pago OAuth returned an error', ['error' => $error]);
    throw new moodle_exception('error:oauthdenied', 'enrol_mercadopagocpro', $returnurl, s($error));
}

if ($state === '' || $code === '') {
    throw new moodle_exception('error:oauthincomplete', 'enrol_mercadopagocpro', $returnurl);
}

$instanceid = oauth_helper::consume_state($state);
if ($instanceid <= 0) {
    throw new moodle_exception('error:oauthstate', 'enrol_mercadopagocpro', $returnurl);
}

$instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'mercadopagocpro'], '*', MUST_EXIST);
$context = context_course::instance($instance->courseid, MUST_EXIST);
require_capability('enrol/mercadopagocpro:config', $context);

$returnurl = new moodle_url('/enrol/instances.php', ['id' => $instance->courseid]);

try {
    $seller = oauth_helper::exchange_code($instanceid, $code);
} catch (api_exception $e) {
    throw new moodle_exception('error:oauthexchange', 'enrol_mercadopagocpro', $returnurl, null, $e->getMessage());
}

// Record the collector id on the instance so it shows in the settings form.
if ($seller->sellerid !== '') {
    $DB->set_field('enrol', 'customchar3', $seller->sellerid, ['id' => $instanceid]);
}

redirect(
    $returnurl,
    get_string('sellerconnected', 'enrol_mercadopagocpro', $seller->sellerid),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
