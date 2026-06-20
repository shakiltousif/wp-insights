# Shakvaro WP Insights — WP SDK

Reusable, opt-in telemetry / usage-analytics library for **any** WordPress plugin. Sends usage and diagnostic data to a Shakvaro Insights backend — only after the user explicitly consents.

- Namespace: `Shakvaro\WP\Insights`
- Privacy-first: opt-in only, OFF by default, two-tier consent, fail-silent, local assets.
- Safe to bundle in many plugins at once: the highest installed version boots once site-wide via version negotiation (no class-redeclare fatals).

## Install

### Via Composer (recommended)

```bash
composer require shakvaro/wp-insights
```

Then make sure your plugin loads Composer's autoloader (which boots the SDK):

```php
require_once __DIR__ . '/vendor/autoload.php';
```

> Composer autoloads only `load.php` (which registers this copy's version). The SDK classes are loaded by the internal version-negotiation loader — NOT by Composer's PSR-4 — so that when several plugins ship different versions, only the newest boots, once, for the whole site.

### Manual (no Composer)

Copy this folder into your plugin and include `load.php`:

```php
require_once __DIR__ . '/vendor/shakvaro-wp-insights/load.php';
```

## Integrate into a plugin

In your main plugin file:

```php
// 1. Register this copy of the SDK (cheap; just records the version).
require_once __DIR__ . '/vendor/shakvaro-wp-insights/load.php';

// 2. Identify the plugin once the SDK has booted.
add_action( 'shakvaro_wp_insights_loaded', function () {
    \Shakvaro\WP\Insights\Insights::register( array(
        'slug'           => 'codecarebd-bkash-nagad-rocket-payoneer-gateway',
        'name'           => 'CodeCareBD - Payment Gateway',
        'version'        => '1.1',
        'plugin_file'    => __FILE__,
        'api_key'        => 'pk_codecarebd_xxx',     // public key (from the backend)
        'signing_secret' => 'the_server_secret_salt', // shared HMAC value (from the backend)
        'endpoint'       => 'https://track.shakvaro.cloud',
        'privacy_url'    => 'https://shakvaro.com/wp-insights/privacy', // shown in the consent notice
    ) )
    ->track_feature( 'gateway_bkash', function () {
        $s = get_option( 'woocommerce_ccd_bkash_settings' );
        return is_array( $s ) && ( $s['enabled'] ?? '' ) === 'yes';
    } )
    ->track_feature( 'advance_payment', function () {
        return get_option( 'ccd_enable_advance_payment', 'no' ) === 'yes';
    } )
    ->add_deactivation_survey();
} );
```

> `api_key` + `signing_secret` come from the backend `plugins` row (the seeder prints them). The signing_secret is the server's `secret_salt`; it ships in the plugin and is used for HMAC request signing (spam-deterrence + rate-limiting, not hard auth).

## Report (re)activations

After a user opts in, deactivates, then reactivates, the SDK cannot detect this automatically (the host isn't loaded when its own activation hook runs). Add one line to report it:

```php
register_activation_hook( __FILE__, function () {
    \Shakvaro\WP\Insights\Insights::mark_activated( 'your-plugin-slug' );
} );
```

`mark_activated()` sets a lightweight flag. The SDK reads and clears it on the next `admin_init` and sends an `activation` ping — consent-gated, fail-silent.

## What it does

- Shows a two-tier opt-in admin notice (usage / marketing). Nothing is sent until the user opts in.
- On usage opt-in: sends an `install` ping, then a weekly `heartbeat` (WP-Cron).
- On (re)activation: sends an `activation` ping (requires `mark_activated()` call above).
- Collects: WP/PHP/MySQL/WooCommerce versions, theme, locale, multisite, server; plugin version; declared feature states; one-way hash of the site URL + site title. Admin email only on marketing opt-in.
- Deactivation survey → `deactivation` ping with reason.
- Opt-out / uninstall → `delete` ping + local cleanup.

## Opt-out from the host plugin

```php
// Wire to a settings toggle:
do_action( 'shakvaro_insights_opt_out_' . 'your-plugin-slug' );
```

## Files

```
load.php                     version-registration shim (always included)
src/Loader.php               boots the highest version once
src/Insights.php             facade (register/plugins/get)
src/Plugin.php               per-plugin controller + fluent API
src/Consent.php              two-tier opt-in notice + state
src/Client.php               fail-silent HMAC-signed transport + payload
src/Scheduler.php            weekly heartbeat (WP-Cron)
src/DeactivationModal.php    "why leaving" survey
src/Uninstall.php            opt-out / cleanup
src/Collectors/*.php         Environment, Lifecycle, Features, Identity
assets/insights.css|js       local-only notice + modal assets
```
