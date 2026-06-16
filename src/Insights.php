<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Facade. Plugins call Insights::register([...]) on `shakvaro_wp_insights_loaded`.
 */
class Insights
{
    /** @var Plugin[] keyed by slug */
    protected static array $plugins = array();

    public static function register(array $config): Plugin
    {
        if (empty($config['slug'])) {
            // Without a slug we cannot do anything meaningful; return a no-op plugin.
            $config['slug'] = 'unknown';
        }

        if (isset(self::$plugins[$config['slug']])) {
            return self::$plugins[$config['slug']];
        }

        $plugin = new Plugin($config);
        self::$plugins[$config['slug']] = $plugin;

        // NOTE: boot() is intentionally NOT called here. The caller configures the
        // plugin with a fluent chain (track_feature/add_deactivation_survey) that
        // runs AFTER register() returns; booting now would miss that config. The
        // Loader boots every registered plugin once registration is complete.

        return $plugin;
    }

    /**
     * Boot every registered plugin. Called by the Loader after the
     * `shakvaro_wp_insights_loaded` action has run (so all fluent config is set).
     */
    public static function boot_all(): void
    {
        foreach (self::$plugins as $plugin) {
            $plugin->boot();
        }
    }

    /** @return Plugin[] */
    public static function plugins(): array
    {
        return self::$plugins;
    }

    public static function get(string $slug): ?Plugin
    {
        return self::$plugins[$slug] ?? null;
    }
}
