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
 * Bootstraps the official Mercado Pago PHP SDK (mercadopago/dx-php).
 *
 * The SDK has no runtime dependencies beyond PHP itself, so it is shipped inside
 * the plugin under vendor/mercadopago/ and loaded through a small PSR-4
 * autoloader. That bundle is the only supported configuration.
 *
 * Composer must not be run inside the plugin directory. Doing so installs a
 * second copy of the SDK and writes a vendor/autoload.php; until v1.1.0 this
 * class preferred that copy, which meant a Composer run silently replaced the
 * audited bundle that thirdpartylibs.xml declares as unmodified upstream. The
 * plugin's own cli/diagnose.php has always reported those files as an error
 * state, so the loader and the diagnostic contradicted each other. The loader
 * was wrong. Development tools belong in the Moodle root's vendor/.
 *
 * @package   enrol_mercadopagocpro
 * @copyright 2026 Julio Tentor & Associates <https://juliotentor.com>
 * @author    Julio Tentor <jtentor@juliotentor.com>
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
    public static function register(): void {
        global $CFG;

        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $plugindir = $CFG->dirroot . '/enrol/mercadopagocpro';

        // The bundled copy of the SDK sources is the only source this plugin
        // loads from. A vendor/autoload.php in this directory is deliberately
        // ignored -- see the class docblock and cli/diagnose.php.
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
                    require_once($real);
                }
            }
        );
    }

    /**
     * Whether the SDK is present and usable.
     *
     * @return bool
     */
    public static function is_available(): bool {
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
    public static function get_version(): ?string {
        if (!self::is_available()) {
            return null;
        }
        // phpcs:ignore moodle.NamingConventions.ValidVariableName -- Third party static property, not ours to rename.
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
    public static function configure(): void {
        if (!self::is_available()) {
            throw new \moodle_exception('error:sdkmissing', 'enrol_mercadopagocpro');
        }
        if (self::$configured) {
            return;
        }
        self::$configured = true;

        \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(\MercadoPago\MercadoPagoConfig::SERVER);

        $maxretries = (int)get_config('enrol_mercadopagocpro', 'apimaxretries');
        if ($maxretries > 0) {
            \MercadoPago\MercadoPagoConfig::setMaxRetries($maxretries);
        }

        $timeout = (int)get_config('enrol_mercadopagocpro', 'apitimeout');
        if ($timeout > 0) {
            \MercadoPago\MercadoPagoConfig::setConnectionTimeout($timeout * 1000);
        }

        $integratorid = trim((string)get_config('enrol_mercadopagocpro', 'integratorid'));
        if ($integratorid !== '') {
            \MercadoPago\MercadoPagoConfig::setIntegratorId($integratorid);
        }

        $platformid = trim((string)get_config('enrol_mercadopagocpro', 'platformid'));
        if ($platformid !== '') {
            \MercadoPago\MercadoPagoConfig::setPlatformId($platformid);
        }
    }
}
