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

    /**
     * Handle plugin uninstall. Call this from the host plugin's uninstall.php.
     *
     * It is intentionally self-contained (no full SDK boot, no Plugin object) so
     * it works in WordPress's minimal uninstall context: it sends a delete/erasure
     * request to the backend (only if the user had opted in), then removes all
     * local options and the scheduled heartbeat. Fail-silent.
     *
     * @param array{slug:string,api_key?:string,signing_secret?:string,endpoint?:string} $config
     */
    public static function uninstall(array $config): void
    {
        if (empty($config['slug']) || ! function_exists('get_option')) {
            return;
        }

        $hash = md5($config['slug']);
        $key = static function (string $suffix) use ($hash) {
            return 'shakvaro_insights_' . $suffix . '_' . $hash;
        };

        $uuid = get_option($key('uuid'));
        $consent = get_option($key('consent'));
        $heartbeat_hook = 'shakvaro_insights_heartbeat_' . $hash;

        // Send a deletion request only if data was actually being shared.
        if ($uuid && is_array($consent) && ! empty($consent['usage'])) {
            try {
                $endpoint = rtrim((string) ($config['endpoint'] ?? 'https://track.shakvaro.cloud'), '/');
                $api_key = (string) ($config['api_key'] ?? '');
                $secret = ! empty($config['signing_secret']) ? (string) $config['signing_secret'] : $api_key;

                $payload = array(
                    'install_uuid' => $uuid,
                    'plugin' => array('slug' => $config['slug']),
                    'reason' => 'uninstall',
                );
                $body = wp_json_encode($payload);

                wp_remote_post($endpoint . '/v1/delete', array(
                    'timeout' => 2,
                    'blocking' => true,
                    'redirection' => 0,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'X-Shakvaro-Key' => $api_key,
                        'X-Shakvaro-Sign' => hash_hmac('sha256', $body, $secret),
                    ),
                    'body' => $body,
                ));
            } catch (\Throwable $e) {
                // Never let uninstall fail because of telemetry.
            }
        }

        // Remove all local state.
        delete_option($key('consent'));
        delete_option($key('uuid'));
        delete_option($key('installed_at'));
        delete_option($key('last_version'));
        delete_option($key('notice_dismissed'));

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook($heartbeat_hook);
        }
    }
}
