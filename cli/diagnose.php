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
 * Diagnoses why the Mercado Pago Checkout Pro enrolment method may not be
 * available, and reports the state of the whole installation.
 *
 * Deliberately avoids get_string() so that it still produces a useful report
 * when the language cache is the thing that is broken.
 *
 * Usage:
 *   php enrol/mercadopagocpro/cli/diagnose.php
 *   php enrol/mercadopagocpro/cli/diagnose.php --courseid=12 --username=jperez
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/ddllib.php');

/**
 * Plugin names this component used before it was renamed. Rows in the enrol table
 * carrying any of these belong to no installed plugin.
 *
 * "mp" is what enrol_plugin::get_name() derived from enrol_mp_checkoutpro_plugin,
 * because core takes the second word of the class name; "mpcheckoutpro" was the
 * name that had to change because the Moodle plugins directory already has one.
 */
define('MERCADOPAGOCPRO_LEGACY_NAMES', ['mp', 'mpcheckoutpro']);

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'courseid' => 0,
        'username' => '',
        'tryadd' => false,
        'keep' => false,
        'cost' => '',
        'name' => '',
        'fixorphans' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error('Unrecognised option: ' . implode(' ', $unrecognised));
}

if ($options['help']) {
    echo <<<EOT
Diagnose the Mercado Pago Checkout Pro enrolment method.

Options:
  -h, --help          Print this help.
      --courseid=N    Also check whether the method can be added to course N.
      --username=U    Check the capabilities of this user instead of the admin.
      --tryadd        Actually create a test instance in that course, then remove it.
      --keep          With --tryadd, leave the created instance in place.
      --cost=N        Cost to use for the save test (default 1000).
      --name=TEXT     Instance name to use for the save test.
      --fixorphans    Delete enrol rows left behind by an earlier name of this plugin.

EOT;
    exit(0);
}

// Number of blocking problems found.
$failures = 0;

// Number of warnings found.
$warnings = 0;

/**
 * Print one check result.
 *
 * @param  bool|null $ok     true = pass, false = fail, null = warning
 * @param  string    $label  what was checked
 * @param  string    $detail what was found
 * @param  string    $fix    what to do about it
 * @return void
 */
function mpcp_report(?bool $ok, string $label, string $detail, string $fix = ''): void {
    global $failures, $warnings;

    if ($ok === true) {
        $mark = '  OK   ';
    } else if ($ok === null) {
        $mark = '  WARN ';
        $warnings++;
    } else {
        $mark = '  FAIL ';
        $failures++;
    }

    echo $mark . str_pad($label, 46) . $detail . PHP_EOL;
    if ($ok !== true && $fix !== '') {
        echo '         -> ' . $fix . PHP_EOL;
    }
}

echo PHP_EOL;
echo '=== enrol_mercadopagocpro diagnostics ===' . PHP_EOL . PHP_EOL;

// 1. Location.
echo '1. Installation' . PHP_EOL;

$expecteddir = $CFG->dirroot . '/enrol/mercadopagocpro';
mpcp_report(
    is_dir($expecteddir),
    'Plugin directory',
    $expecteddir,
    'The directory must be named exactly "mercadopagocpro" and sit inside the enrol '
        . 'directory of $CFG->dirroot (on Moodle 5.x that is usually .../public/enrol/).'
);

mpcp_report(
    file_exists($expecteddir . '/version.php'),
    'version.php present',
    file_exists($expecteddir . '/version.php') ? 'found' : 'MISSING',
    'The zip was probably unpacked one level too deep. enrol/mercadopagocpro/version.php must exist.'
);

$diskversion = null;
if (file_exists($expecteddir . '/version.php')) {
    $plugin = new stdClass();
    require($expecteddir . '/version.php');
    $diskversion = $plugin->version ?? null;
    $requires = $plugin->requires ?? null;

    mpcp_report(
        ($plugin->component ?? '') === 'enrol_mercadopagocpro',
        'Component name in version.php',
        (string)($plugin->component ?? '(none)'),
        'It must be exactly enrol_mercadopagocpro and match the directory name.'
    );

    mpcp_report(
        $requires === null || $CFG->version >= $requires,
        'Moodle version requirement',
        'site ' . $CFG->version . ' / requires ' . $requires,
        'This Moodle is older than the plugin requires, so the installer refuses it. '
            . 'Upgrade Moodle, or lower $plugin->requires in version.php if you know the '
            . 'APIs it uses are present.'
    );
}

$dbversion = get_config('enrol_mercadopagocpro', 'version');
mpcp_report(
    !empty($dbversion),
    'Installed in the database',
    $dbversion ? (string)$dbversion : 'NOT INSTALLED',
    'Visit Site administration > Notifications, or run php admin/cli/upgrade.php, to finish the install.'
);

if ($dbversion && $diskversion && (float)$dbversion < (float)$diskversion) {
    mpcp_report(
        null,
        'Pending upgrade',
        'db ' . $dbversion . ' < disk ' . $diskversion,
        'Run php admin/cli/upgrade.php.'
    );
}

// 2. Classes.
echo PHP_EOL . '2. Code loading' . PHP_EOL;

$classloadable = class_exists('enrol_mercadopagocpro_plugin');
mpcp_report(
    $classloadable,
    'Class enrol_mercadopagocpro_plugin',
    $classloadable ? 'loadable' : 'NOT LOADABLE',
    'This is the silent killer: enrol_get_plugins() skips a plugin whose class cannot be '
        . 'loaded, with no error at all. Run php admin/cli/purge_caches.php. If it still '
        . 'fails, check that classes/plugin.php exists and has no parse error.'
);

$instance = enrol_get_plugin('mercadopagocpro');
mpcp_report(
    $instance !== null,
    'enrol_get_plugin("mercadopagocpro")',
    $instance !== null ? 'returns an instance' : 'returns null',
    'Same cause as above.'
);

if ($instance !== null) {
    mpcp_report(
        $instance->use_standard_editing_ui(),
        'use_standard_editing_ui()',
        $instance->use_standard_editing_ui() ? 'true' : 'false'
    );

    // Core's enrol_plugin::get_name() does explode('_', get_class($this))[1]. The plugin
    // name deliberately has no underscore, so core derives "mercadopagocpro" by
    // itself. A name like mp_checkoutpro would resolve to "mp" instead, and every
    // instance would be stored under a plugin that does not exist and vanish from
    // the course. This is the regression guard for that.
    $pluginname = $instance->get_name();
    mpcp_report(
        $pluginname === 'mercadopagocpro',
        'get_name()',
        '"' . $pluginname . '"',
        'It must return "mercadopagocpro". Core derives the name from the second word '
            . 'of the class name, so the plugin directory must never contain an '
            . 'underscore. Check the directory name and the class name.'
    );

    // Rows left behind by an earlier name of this plugin. "mp" came from
    // enrol_mp_checkoutpro, whose underscore made get_name() resolve to "mp";
    // "mpcheckoutpro" was the name before the move to enrol_mercadopagocpro.
    // Either way the rows belong to no installed plugin, so the course interface
    // cannot edit or remove them.
    foreach (MERCADOPAGOCPRO_LEGACY_NAMES as $legacy) {
        $orphans = $DB->count_records('enrol', ['enrol' => $legacy]);
        mpcp_report(
            $orphans === 0 ? true : null,
            'Orphan enrol rows with enrol="' . $legacy . '"',
            $orphans . ' found',
            'Left behind by an earlier name of this plugin. They belong to no '
                . 'plugin, so they cannot be edited or removed from the course '
                . 'interface. Run this script with --fixorphans to delete them.'
        );
    }
}

// 3. Enabled.
echo PHP_EOL . '3. Enabled state' . PHP_EOL;

$enabledlist = isset($CFG->enrol_plugins_enabled) ? explode(',', $CFG->enrol_plugins_enabled) : [];
$isenabled = enrol_is_enabled('mercadopagocpro');
mpcp_report(
    $isenabled,
    'Enrolment method enabled',
    $isenabled ? 'enabled' : 'DISABLED',
    'Site administration > Plugins > Enrolments > Manage enrol plugins, then click the eye '
        . 'icon on "Mercado Pago Checkout Pro". Only enabled methods appear in the '
        . '"Add method" dropdown of a course.'
);
echo '         enrol_plugins_enabled = ' . implode(', ', $enabledlist) . PHP_EOL;

// 4. Capabilities.
echo PHP_EOL . '4. Capabilities' . PHP_EOL;

$capsindb = $DB->get_fieldset_select(
    'capabilities',
    'name',
    $DB->sql_like('name', ':pattern'),
    ['pattern' => 'enrol/mercadopagocpro:%']
);
mpcp_report(
    count($capsindb) >= 6,
    'Capabilities registered',
    count($capsindb) . ' found',
    'The capabilities from db/access.php were not installed. Run php admin/cli/upgrade.php '
        . 'and then php admin/cli/purge_caches.php.'
);
foreach ($capsindb as $cap) {
    echo '         ' . $cap . PHP_EOL;
}

$checkuser = null;
if ($options['username'] !== '') {
    $checkuser = $DB->get_record('user', ['username' => $options['username'], 'deleted' => 0]);
    if (!$checkuser) {
        cli_error('No user with username "' . $options['username'] . '".');
    }
}

/**
 * Announce a section that is being skipped, and say what would run it.
 *
 * Without this the numbering jumps -- 4 straight to 6, 8 straight to 11 -- and
 * a reader reasonably assumes something failed silently. Say so instead.
 *
 * @param  string $number Section number as printed, e.g. '5' or '9'.
 * @param  string $title  Section title.
 * @param  string $needs  What the caller has to supply, e.g. '--courseid=N'.
 * @return void
 */
function mercadopagocpro_section_skipped(string $number, string $title, string $needs): void {
    echo PHP_EOL . $number . '. ' . $title . PHP_EOL;
    echo '  SKIP not run                                  pass ' . $needs . PHP_EOL;
}

if ($options['courseid']) {
    $course = $DB->get_record('course', ['id' => (int)$options['courseid']]);
    if (!$course) {
        cli_error('No course with id ' . $options['courseid'] . '.');
    }
    $coursecontext = context_course::instance($course->id);

    // A CLI script starts with no logged in user ($USER->id == 0), and
    // can_add_instance() reads $USER internally. Without this the check below
    // would always fail no matter who can really add the method.
    $asuser = $checkuser ?: get_admin();
    \core\session\manager::set_user($asuser);
    $wholabel = $asuser->username;

    echo PHP_EOL . '5. Course ' . $course->id . ' (' . $course->shortname . '), evaluated as ' .
        $wholabel . PHP_EOL;

    if (!$checkuser) {
        echo '         (no --username given, so this is the site admin; pass --username=U ' .
            'to test the person who actually cannot see the method)' . PHP_EOL;
    }

    $canenrolconfig = has_capability('moodle/course:enrolconfig', $coursecontext);
    mpcp_report(
        $canenrolconfig,
        'moodle/course:enrolconfig',
        $canenrolconfig ? 'allowed for ' . $wholabel : 'DENIED for ' . $wholabel,
        'Without it no enrolment method can be added at all.'
    );

    $canconfig = has_capability('enrol/mercadopagocpro:config', $coursecontext);
    mpcp_report(
        $canconfig,
        'enrol/mercadopagocpro:config',
        $canconfig ? 'allowed for ' . $wholabel : 'DENIED for ' . $wholabel,
        'By default this capability is granted to Manager only, exactly like enrol/fee. '
            . 'An editing teacher can add "Self enrolment" but not this method. Either use a '
            . 'Manager account, or allow enrol/mercadopagocpro:config for the editing teacher '
            . 'role in Site administration > Users > Permissions > Define roles.'
    );

    if ($instance !== null) {
        $canadd = $instance->can_add_instance($course->id);
        mpcp_report(
            $canadd,
            'can_add_instance()',
            $canadd ? 'true' : 'FALSE',
            'This is what decides whether the method shows in the "Add method" dropdown.'
        );
    }

    $existing = $DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'mercadopagocpro']);
    echo '         existing instances in this course: ' . $existing . PHP_EOL;

    // Reproduce exactly what enrol/instances.php does to build the dropdown.
    // This is the ground truth: if mercadopagocpro is listed here, the browser
    // will list it too for this user.
    echo PHP_EOL . '5b. Simulated "Add method" dropdown' . PHP_EOL;

    $candidates = [];
    foreach (enrol_get_plugins(true) as $name => $candidate) {
        if ($candidate->use_standard_editing_ui()) {
            if ($candidate->can_add_instance($course->id)) {
                $candidates[] = $name;
            }
        } else if ($candidate->get_newinstance_link($course->id)) {
            $candidates[] = $name . ' (custom UI)';
        }
    }

    mpcp_report(
        in_array('mercadopagocpro', $candidates, true),
        'mercadopagocpro offered in the dropdown',
        in_array('mercadopagocpro', $candidates, true) ? 'yes' : 'NO',
        'If every check above passed and this still says NO, the cause is outside this '
            . 'plugin. Compare with the other methods listed below.'
    );
    echo '         dropdown would contain: ' . (($candidates) ? implode(', ', $candidates) : '(nothing)') . PHP_EOL;
}

// 6. Environment.
if (!$options['courseid']) {
    mercadopagocpro_section_skipped('5', 'Course evaluation', '--courseid=N');
    mercadopagocpro_section_skipped('5b', 'Simulated "Add method" dropdown', '--courseid=N');
}

echo PHP_EOL . '6. Runtime prerequisites' . PHP_EOL;

$https = strpos((string)$CFG->wwwroot, 'https://') === 0;
mpcp_report(
    $https,
    'Site served over HTTPS',
    $CFG->wwwroot,
    'The method can be added but not enabled without HTTPS: Mercado Pago rejects http '
        . 'notification_url and back_urls.'
);

$sdkok = false;
if ($classloadable) {
    $sdkok = \enrol_mercadopagocpro\local\sdk::is_available();
}
$loadedversion = $sdkok ? \enrol_mercadopagocpro\local\sdk::get_version() : null;
mpcp_report(
    $sdkok,
    'Mercado Pago PHP SDK',
    $sdkok ? 'version ' . $loadedversion : 'NOT FOUND',
    'vendor/mercadopago/src/MercadoPago is missing from the plugin directory. Some zip '
        . 'tools skip a directory named vendor. Restore it.'
);

// The version that actually loaded must be the one thirdpartylibs.xml declares.
// Running composer inside the plugin directory creates vendor/autoload.php, which
// sdk::register() prefers over the bundled sources, so a second copy of the SDK
// silently shadows the audited one. thirdpartylibs.xml is the source of truth.
$declaredversion = null;
$tplfile = $expecteddir . '/thirdpartylibs.xml';
if (file_exists($tplfile)) {
    $tpl = @simplexml_load_file($tplfile);
    if ($tpl !== false) {
        foreach ($tpl->library as $lib) {
            if (strpos((string)$lib->location, 'mercadopago') !== false) {
                $declaredversion = trim((string)$lib->version);
            }
        }
    }
}
if ($sdkok && $declaredversion !== null) {
    mpcp_report(
        $loadedversion === $declaredversion,
        'SDK version matches thirdpartylibs.xml',
        'loaded ' . $loadedversion . ', declared ' . $declaredversion,
        'The SDK that loaded is not the one this plugin ships and audits. The usual '
            . 'cause is a composer install run inside enrol/mercadopagocpro, which '
            . 'creates vendor/autoload.php and a second copy of the SDK under '
            . 'vendor/mercadopago/dx-php. sdk::register() prefers that autoloader, so '
            . 'the bundled sources are ignored. Remove vendor/autoload.php, '
            . 'vendor/composer and vendor/mercadopago/dx-php, or update '
            . 'thirdpartylibs.xml to match.'
    );
}

// Development tooling must never travel inside a released plugin.
$strays = [];
foreach (
    ['vendor/autoload.php', 'vendor/composer', 'vendor/mercadopago/dx-php',
    'vendor/squizlabs', 'vendor/moodlehq', 'vendor/phpcsstandards', 'composer.lock'] as $stray
) {
    if (file_exists($expecteddir . '/' . $stray)) {
        $strays[] = $stray;
    }
}
mpcp_report(
    empty($strays) ? true : null,
    'No composer artefacts in the plugin directory',
    empty($strays) ? 'clean' : implode(', ', $strays),
    'These come from running composer inside enrol/mercadopagocpro. The plugin ships '
        . 'the SDK sources directly and needs no composer install; development tools '
        . 'belong in the Moodle root vendor directory. Delete them before packaging.'
);

mpcp_report(
    version_compare(PHP_VERSION, '8.2', '>='),
    'PHP version',
    PHP_VERSION,
    'The Mercado Pago SDK requires PHP 8.2 or later.'
);

foreach (['curl', 'json', 'openssl'] as $ext) {
    mpcp_report(extension_loaded($ext), 'PHP extension ' . $ext, extension_loaded($ext) ? 'loaded' : 'MISSING');
}
mpcp_report(
    extension_loaded('sodium') ? true : null,
    'PHP extension sodium',
    extension_loaded('sodium') ? 'loaded' : 'missing',
    'Only needed for per-course credentials, which are encrypted with \core\encryption.'
);

// 7. Database.
echo PHP_EOL . '7. Database tables' . PHP_EOL;

$dbman = $DB->get_manager();
foreach (['enrol_mercadopagocpro_txn', 'enrol_mercadopagocpro_wh', 'enrol_mercadopagocpro_cred'] as $table) {
    $exists = $dbman->table_exists(new xmldb_table($table));
    mpcp_report(
        $exists,
        'Table ' . $table,
        $exists ? 'present' : 'MISSING',
        'The install step did not run. php admin/cli/upgrade.php.'
    );
}

// 8. Credentials.
echo PHP_EOL . '8. Configuration' . PHP_EOL;

if ($classloadable) {
    $credentials = \enrol_mercadopagocpro\local\credentials::resolve(null);
    mpcp_report(
        $credentials->is_usable(),
        'Access token resolved',
        $credentials->is_usable()
            ? 'yes (source: ' . $credentials->get_source() . ', env: ' . $credentials->get_environment() . ')'
            : 'NO',
        'Set it in the plugin settings, in $CFG->enrol_mercadopagocpro, or in '
            . 'MERCADOPAGOCPRO_ACCESS_TOKEN. Remember that the Environment switch selects '
            . 'between the production and the test token.'
    );
    mpcp_report(
        $credentials->can_validate_signature() ? true : null,
        'Webhook secret configured',
        $credentials->can_validate_signature() ? 'yes' : 'no',
        'Without it, incoming notifications cannot be verified and are rejected with 401.'
    );
}

$tasks = $DB->get_fieldset_select(
    'task_scheduled',
    'classname',
    $DB->sql_like('classname', ':pattern'),
    ['pattern' => '%enrol_mercadopagocpro%']
);
mpcp_report(
    count($tasks) >= 4,
    'Scheduled tasks registered',
    count($tasks) . ' found',
    'Run php admin/cli/upgrade.php. Without reconcile_payments a lost notification strands a payment.'
);

// 9. Instance form smoke test.
// enrol/editinstance.php is what actually runs when you pick the method from the
// dropdown, and edit_instance_form() only ever executes in the browser. Building
// the form here reproduces that code path and surfaces any fatal it would throw.
if (!($options['courseid'] && $instance !== null)) {
    mercadopagocpro_section_skipped('9', 'Instance form smoke test', '--courseid=N --tryadd');
}

if ($options['courseid'] && $instance !== null) {
    echo PHP_EOL . '9. Instance form smoke test' . PHP_EOL;

    require_once($CFG->libdir . '/formslib.php');

    $course = $DB->get_record('course', ['id' => (int)$options['courseid']], '*', MUST_EXIST);
    $coursecontext = context_course::instance($course->id);

    // Exactly the stub enrol/editinstance.php builds for a brand new instance.
    $stub = (object)$instance->get_instance_defaults();
    $stub->id = null;
    $stub->courseid = $course->id;
    $stub->status = ENROL_INSTANCE_ENABLED;

    try {
        $PAGE->set_context($coursecontext);
        $mform = new MoodleQuickForm('mpcpdiag', 'post', new moodle_url('/enrol/editinstance.php'));
        $instance->edit_instance_form($stub, $mform, $coursecontext);
        $formbuilt = true;

        $count = count($mform->_elements ?? []);
        mpcp_report(
            $count > 0,
            'edit_instance_form() builds',
            $count . ' element(s), no exception'
        );
    } catch (Throwable $e) {
        mpcp_report(
            false,
            'edit_instance_form() builds',
            get_class($e) . ': ' . $e->getMessage(),
            'This is the error you get when you pick the method from the dropdown. '
                . 'Thrown at ' . $e->getFile() . ':' . $e->getLine()
        );
        echo PHP_EOL . '         Stack trace:' . PHP_EOL;
        foreach (array_slice(explode("\n", $e->getTraceAsString()), 0, 12) as $line) {
            echo '           ' . $line . PHP_EOL;
        }
        unset($mform);
    }
}

// 10. Save path smoke test.
// Reproduces what happens when you fill the form and press "Add method":
// edit_instance_validation() first, then add_instance(). The submitted data is
// derived from the real form built in section 9, so every field the browser
// posts is present - not just the handful a hand written array would cover.
if (!($options['courseid'] && $instance !== null && isset($mform))) {
    mercadopagocpro_section_skipped('10', 'Save path smoke test', '--courseid=N --tryadd');
}

if ($options['courseid'] && $instance !== null && isset($mform)) {
    echo PHP_EOL . '10. Save path smoke test' . PHP_EOL;

    $course = $DB->get_record('course', ['id' => (int)$options['courseid']], '*', MUST_EXIST);
    $coursecontext = context_course::instance($course->id);

    // Every element the form declared, with the default the browser would post.
    $data = [];
    foreach (($mform->_elements ?? []) as $element) {
        $name = $element->getName();
        if ($name === null || $name === '') {
            continue;
        }
        $type = $element->getType();
        if (in_array($type, ['header', 'static', 'html', 'submit', 'cancel', 'hidden'], true)) {
            continue;
        }
        if (array_key_exists($name, $mform->_defaultValues ?? [])) {
            $data[$name] = $mform->_defaultValues[$name];
            continue;
        }
        // No default set. The browser still posts something, and for numeric
        // widgets that something is 0, not ''. Feeding '' here would trip
        // validate_param_types() (in PHP 8, '' != 0) and report a phantom error.
        $numeric = ['select', 'duration', 'date_time_selector', 'advcheckbox', 'checkbox', 'radio'];
        $data[$name] = in_array($type, $numeric, true) ? 0 : '';
    }

    $studentrole = get_archetype_roles('student');
    $studentrole = $studentrole ? reset($studentrole) : null;
    $roleid = (int)($data['roleid'] ?? 0) ?: (int)(get_config('enrol_mercadopagocpro', 'roleid')
        ?: ($studentrole->id ?? 0));

    // What the operator actually types in.
    $data['name'] = $options['name'] !== '' ? $options['name'] : 'Diagnostic test method';
    $data['cost'] = (string)($options['cost'] !== '' ? $options['cost'] : '1000');
    $data['status'] = ENROL_INSTANCE_ENABLED;
    $data['roleid'] = $roleid;
    $data['currency'] = (string)($data['currency'] ?: (get_config('enrol_mercadopagocpro', 'currency') ?: 'ARS'));

    echo '         fields posted: ' . implode(', ', array_keys($data)) . PHP_EOL;
    echo '         name="' . $data['name'] . '", cost=' . $data['cost'] . ' ' . $data['currency'] .
        ', status=enabled, roleid=' . $roleid . PHP_EOL;

    $stub = (object)$instance->get_instance_defaults();
    $stub->id = null;
    $stub->courseid = $course->id;
    $stub->status = ENROL_INSTANCE_ENABLED;

    $errors = $instance->edit_instance_validation($data, [], $stub, $coursecontext);

    mpcp_report(
        empty($errors),
        'edit_instance_validation()',
        empty($errors) ? 'no errors' : count($errors) . ' error(s) - THIS is what blocks the save',
        'The browser redisplays the form with these messages attached to their fields. '
            . 'If a field sits inside a collapsed section you may not notice it - expand '
            . 'every section on the form and look for red text.'
    );
    foreach ($errors as $field => $message) {
        echo '           ' . str_pad($field, 22) . $message . PHP_EOL;
    }

    if (!$options['tryadd']) {
        echo '         (add --tryadd to actually create an instance and then remove it again)' . PHP_EOL;
    } else if (!empty($errors)) {
        echo '         not attempting add_instance(): validation already failed' . PHP_EOL;
    } else {
        $before = (int)$DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'mercadopagocpro']);
        try {
            $newid = $instance->add_instance($course, $data);
            $after = (int)$DB->count_records('enrol', ['courseid' => $course->id, 'enrol' => 'mercadopagocpro']);
            $record = $newid ? $DB->get_record('enrol', ['id' => $newid]) : null;

            mpcp_report(
                $record !== null,
                'add_instance()',
                $record !== null
                    ? 'created instance id ' . $newid . ', cost ' . $record->cost . ' ' . $record->currency
                    . ', status ' . ((int)$record->status === ENROL_INSTANCE_ENABLED ? 'enabled' : 'disabled')
                    . ' (rows ' . $before . ' -> ' . $after . ')'
                    : 'RETURNED NOTHING'
            );

            if ($record !== null && !$options['keep']) {
                $instance->delete_instance($record);
                echo '         test instance removed again' . PHP_EOL;
            } else if ($record !== null) {
                echo '         test instance KEPT (--keep) - it should now be visible in the course' . PHP_EOL;
            }
        } catch (Throwable $e) {
            mpcp_report(
                false,
                'add_instance()',
                get_class($e) . ': ' . $e->getMessage(),
                'Thrown at ' . $e->getFile() . ':' . $e->getLine()
            );
        }
    }

    // What the course actually holds right now.
    echo PHP_EOL . '         instances currently in this course:' . PHP_EOL;
    $all = $DB->get_records('enrol', ['courseid' => $course->id], 'sortorder ASC');
    foreach ($all as $row) {
        echo '           id=' . str_pad((string)$row->id, 5) . str_pad($row->enrol, 18)
            . 'status=' . ((int)$row->status === ENROL_INSTANCE_ENABLED ? 'enabled ' : 'disabled')
            . '  name=' . ($row->name ?: '(default)') . PHP_EOL;
    }
}

// 11. Orphan cleanup.
if (!$options['fixorphans']) {
    mercadopagocpro_section_skipped('11', 'Orphan cleanup', '--fixorphans');
}

if ($options['fixorphans']) {
    echo PHP_EOL . '11. Orphan cleanup' . PHP_EOL;

    [$insql, $inparams] = $DB->get_in_or_equal(MERCADOPAGOCPRO_LEGACY_NAMES);
    $orphanrows = $DB->get_records_select('enrol', 'enrol ' . $insql, $inparams);
    if (!$orphanrows) {
        echo '         nothing to clean up' . PHP_EOL;
    } else {
        foreach ($orphanrows as $row) {
            $ues = $DB->count_records('user_enrolments', ['enrolid' => $row->id]);
            $DB->delete_records('user_enrolments', ['enrolid' => $row->id]);
            $DB->delete_records('enrol', ['id' => $row->id]);
            echo '         deleted enrol id ' . $row->id . ' (enrol="' . $row->enrol
                . '", course ' . $row->courseid . ', ' . $ues . ' user enrolment(s)) name="'
                . ($row->name ?: '(default)') . '"' . PHP_EOL;
        }
        echo '         ' . count($orphanrows) . ' orphan row(s) removed' . PHP_EOL;
    }
}

// Conclusion.
echo PHP_EOL . '=== Result: ' . $failures . ' failure(s), ' . $warnings . ' warning(s) ===' . PHP_EOL;

if ($failures === 0) {
    echo PHP_EOL . 'Nothing blocking found. If the method still does not appear in a course, '
        . 're-run with --courseid=N --username=U for the exact course and person that cannot see it.'
        . PHP_EOL . PHP_EOL;
    exit(0);
}

echo PHP_EOL . 'Fix the FAIL lines from the top down: each one can hide the ones below it.'
    . PHP_EOL . PHP_EOL;
exit(1);
