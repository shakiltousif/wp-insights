<?php

namespace Shakvaro\WP\Insights\Collectors;

use Shakvaro\WP\Insights\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Lifecycle data tied to the host plugin (version + activation age).
 */
class Lifecycle
{
    public static function collect(Plugin $plugin): array
    {
        $installed_at = (int) get_option($plugin->option_key('installed_at'), 0);
        $days_active = $installed_at > 0 ? (int) floor((time() - $installed_at) / DAY_IN_SECONDS) : 0;

        return array(
            'plugin_version' => $plugin->config('version'),
            'days_active' => $days_active,
        );
    }
}
