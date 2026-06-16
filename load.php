<?php
/**
 * Shakvaro WP Insights — bootstrap shim.
 *
 * Every plugin that bundles this library includes ONLY this file. It registers
 * this copy's version into a global registry, then (once) schedules the boot of
 * the HIGHEST registered version on `plugins_loaded`. This guarantees that when
 * several Shakvaro plugins ship different SDK versions on the same site, only
 * the newest copy's classes load — once — for the whole site. No Composer, no
 * class-redeclare fatals.
 *
 * @package Shakvaro\WP\Insights
 */

if (! defined('ABSPATH')) {
    exit;
}

// This copy's version — a LOCAL variable, never a global constant, so each
// bundled copy registers its OWN version (a shared constant would make every
// copy report whichever loaded first, breaking version negotiation).
// Bump this literal on every SDK release.
$shakvaro_wp_insights_this_version = '1.2.3';

if (! isset($GLOBALS['shakvaro_wp_insights_versions']) || ! is_array($GLOBALS['shakvaro_wp_insights_versions'])) {
    $GLOBALS['shakvaro_wp_insights_versions'] = array();
}

// Register this copy: path => version.
$GLOBALS['shakvaro_wp_insights_versions'][__DIR__] = $shakvaro_wp_insights_this_version;
unset($shakvaro_wp_insights_this_version);

if (! function_exists('shakvaro_wp_insights_boot')) {
    /**
     * Pick the highest registered version and boot it once.
     */
    function shakvaro_wp_insights_boot()
    {
        if (defined('SHAKVARO_WP_INSIGHTS_BOOTED')) {
            return;
        }

        $versions = $GLOBALS['shakvaro_wp_insights_versions'];

        // Sort ascending by semantic version; tie-break by path for determinism.
        uksort($versions, function ($a, $b) use ($versions) {
            $cmp = version_compare($versions[$a], $versions[$b]);

            return 0 !== $cmp ? $cmp : strcmp($a, $b);
        });

        $winner_dir = array_key_last($versions);

        require_once $winner_dir . '/src/Loader.php';

        define('SHAKVARO_WP_INSIGHTS_BOOTED', $versions[$winner_dir]);

        \Shakvaro\WP\Insights\Loader::init($winner_dir);
    }

    // Priority 1: after most plugins have registered, before normal init.
    add_action('plugins_loaded', 'shakvaro_wp_insights_boot', 1);
}
