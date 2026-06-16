<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads the winning SDK copy's classes once and signals readiness.
 */
class Loader
{
    protected static string $dir = '';

    public static function init(string $dir): void
    {
        self::$dir = $dir;

        $classes = array(
            'Client',
            'Consent',
            'Scheduler',
            'DeactivationModal',
            'Uninstall',
            'Plugin',
            'Insights',
            'SettingsPage',
            'Collectors/Environment',
            'Collectors/Lifecycle',
            'Collectors/Features',
            'Collectors/Identity',
        );

        foreach ($classes as $class) {
            require_once $dir . '/src/' . $class . '.php';
        }

        /**
         * Fired once the SDK is booted. Each plugin hooks this to register itself
         * via Insights::register(); it fires regardless of which copy won.
         */
        do_action('shakvaro_wp_insights_loaded');

        // All plugins are now registered AND fully configured (their fluent
        // chains ran synchronously inside the action above) — boot them.
        Insights::boot_all();

        // One shared "Data Sharing" settings page for all registered plugins.
        SettingsPage::init();
    }

    public static function dir(): string
    {
        return self::$dir;
    }

    public static function url(): string
    {
        // Resolve a URL to the booted copy for local assets.
        return plugins_url('', self::$dir . '/load.php');
    }
}
