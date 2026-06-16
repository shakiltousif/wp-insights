<?php

namespace Shakvaro\WP\Insights\Collectors;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Gathers the site environment (no PII).
 */
class Environment
{
    public static function collect(): array
    {
        global $wp_version, $wpdb;

        $wc_version = defined('WC_VERSION') ? WC_VERSION : null;

        return array(
            'wp_version' => $wp_version ?? null,
            'php_version' => PHP_VERSION,
            'mysql_version' => isset($wpdb) ? $wpdb->db_version() : null,
            'wc_version' => $wc_version,
            'active_theme' => function_exists('wp_get_theme') ? (string) wp_get_theme()->get('Name') : null,
            'locale' => function_exists('get_locale') ? get_locale() : null,
            'is_multisite' => function_exists('is_multisite') ? is_multisite() : false,
            'server_software' => isset($_SERVER['SERVER_SOFTWARE'])
                ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))
                : null,
        );
    }
}
