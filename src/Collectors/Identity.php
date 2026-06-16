<?php

namespace Shakvaro\WP\Insights\Collectors;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Site identity. Raw URL is NEVER sent — only a one-way hash. Admin email is
 * only included when marketing consent is granted.
 */
class Identity
{
    public static function url_hash(): string
    {
        return hash('sha256', home_url());
    }

    public static function site_title(): string
    {
        return (string) get_bloginfo('name');
    }

    public static function admin_email(): string
    {
        return (string) get_option('admin_email');
    }
}
