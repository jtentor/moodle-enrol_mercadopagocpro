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

/**
 * Bootstraps the official Mercado Pago PHP SDK (mercadopago/dx-php).
 *
 * The SDK has no runtime dependencies beyond PHP itself, so it is shipped inside
 * the plugin under vendor/mercadopago/ and loaded through a small PSR-4
 * autoloader. If the site prefers to manage it with Composer, a
 * vendor/autoload.php inside the plugin directory takes precedence.
 *
 * @package   enrol_mpcheckoutpro
 * @copyright 2026 Julio Tentor <jtentor@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @see       https://github.com/mercadopago/sdk-php
 */
final class sdk
{

    /**
     * @var bool Whether the autoloader has already been registered. 
     */
    private static bool $registered = false;

    /**
     * @var bool Whether global SDK configuration has been applied. 
     */
    private static bool $configured = false;

    /**
     * Make the MercadoPago\* classes loadable.
     *
     * @return void
     */
    public static function register(): void
    {
        global $CFG;

        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $plugindir = $CFG->dirroot . '/enrol/mpcheckoutpro';

        // 1. Composer managed installation inside the plugin.
        $composerautoload = $plugindir . '/vendor/autoload.php';
        if (file_exists($composerautoload)) {
            require_once $composerautoload;
            return;
        }

        // 2. Bundled copy of the SDK sources.
        $bundled = $plugindir . '/vendor/mercadopago/src/MercadoPago';
        if (!is_dir($bundled)) {
            return;
        }

        spl_autoload_register(
            static function (string $class) use ($bundled): void {
                $prefix = 'MercadoPago\\';
                if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                    return;
                }
                $relative = substr($class, strlen($prefix));
                $path = $bundled . '/' . str_replace('\\', '/', $relative) . '.php';
                // Guard against traversal coming from a crafted class name.
                $real = realpath($path);
                if ($real !== false && strpos($real, realpath($bundled)) === 0) {
                    require_once $real;
                }
            }
        );
    }

    /**
     * Whether the SDK is present and usable.
     *
     * @return bool
     */
    public static function is_available(): bool
    {
        self::register();
        return class_exists('\MercadoPago\MercadoPagoConfig')
            && class_exists('\MercadoPago\Client\Preference\PreferenceClient')
            && class_exists('\MercadoPago\Client\Payment\PaymentClient');
    }

    /**
     * Version of the bundled SDK, or null when it is not available.
     *
     * @return string|null
     */
    public static function get_version(): ?string
    {
        if (!self::is_available()) {
            return null;
        }
        return \MercadoPago\MercadoPagoConfig::$CURRENT_VERSION;
    }

    /**
     * Apply the SDK wide configuration this plugin relies on.
     *
     * The access token is deliberately NOT set globally: every call passes its own
     * token through RequestOptions so that two courses using different sellers can
     * never leak credentials into each other's requests.
     *
     * @return void
     * @throws \moodle_exception when the SDK is missing.
     */
    public static function configure(): void
    {
        if (!self::is_available()) {
            throw new \moodle_exception('error:sdkmissing', 'enrol_mpcheckoutpro');
        }
        if (self::$configured) {
            return;
        }
        self::$configured = true;

        \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(\MercadoPago\MercadoPagoConfig::SERVER);

        $maxretries = (int)get_config('enrol_mpcheckoutpro', 'apimaxretries');
        if ($maxretries > 0) {
            \MercadoPago\MercadoPagoConfig::setMaxRetries($maxretries);
        }

        $timeout = (int)get_config('enrol_mpcheckoutpro', 'apitimeout');
        if ($timeout > 0) {
            \MercadoPago\MercadoPagoConfig::setConnectionTimeout($timeout * 1000);
        }

        $integratorid = trim((string)get_config('enrol_mpcheckoutpro', 'integratorid'));
        if ($integratorid !== '') {
            \MercadoPago\MercadoPagoConfig::setIntegratorId($integratorid);
        }

        $platformid = trim((string)get_config('enrol_mpcheckoutpro', 'platformid'));
        if ($platformid !== '') {
            \MercadoPago\MercadoPagoConfig::setPlatformId($platformid);
        }
    }
}
