<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Opt-out + uninstall handling. Provides a programmatic opt-out the host plugin
 * can wire to a settings toggle, and a delete on uninstall.
 */
class Uninstall
{
    public function __construct(protected Plugin $plugin) {}

    public function init(): void
    {
        // Allow a host plugin to opt the user out programmatically.
        add_action('shakvaro_insights_opt_out_' . $this->plugin->slug(), array($this, 'opt_out'));
    }

    /**
     * Withdraw consent, request server-side deletion, and clean local options.
     */
    public function opt_out(): void
    {
        $this->plugin->client()->send_delete('opt_out');
        $this->cleanup();
    }

    public function cleanup(): void
    {
        delete_option($this->plugin->option_key('consent'));
        delete_option($this->plugin->option_key('uuid'));
        delete_option($this->plugin->option_key('installed_at'));

        $timestamp = wp_next_scheduled('shakvaro_insights_heartbeat_' . md5($this->plugin->slug()));
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'shakvaro_insights_heartbeat_' . md5($this->plugin->slug()));
        }
    }
}
