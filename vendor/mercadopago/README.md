# Mercado Pago PHP SDK (bundled)

This directory contains an unmodified copy of the official Mercado Pago SDK for
PHP (`mercadopago/dx-php`), version 3.14.0, released under the MIT licence.

* Upstream: <https://github.com/mercadopago/sdk-php>
* Licence: MIT (see LICENSE)

It is bundled so the plugin can be installed on sites that do not run Composer.
`enrol_mercadopagocpro\local\sdk` registers a PSR-4 autoloader for the
`MercadoPago\` namespace pointing at `src/MercadoPago`.

If you prefer to manage the dependency with Composer, run

    composer install --no-dev

inside `enrol/mercadopagocpro`. When `enrol/mercadopagocpro/vendor/autoload.php`
exists it takes precedence over this bundled copy.

Do not edit these files: replace the whole directory when you upgrade the SDK,
and update the version recorded in `thirdpartylibs.xml`.
