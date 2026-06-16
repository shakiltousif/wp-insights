<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Schedules the weekly heartbeat via WP-Cron (only while usage consent holds).
 */
class Scheduler
{
    protected string $hook;

    public function __construct(protected Plugin $plugin)
    {
        // Per-plugin hook so multiple plugins each get their own heartbeat.
        $this->hook = 'shakvaro_insights_heartbeat_' . md5($plugin->slug());
    }

    public function init(): void
    {
        add_filter('cron_schedules', array($this, 'register_weekly')); // phpcs:ignore WordPress.WP.CronInterval
        add_action($this->hook, array($this, 'run'));
        add_action('init', array($this, 'maybe_schedule'));
        add_action('admin_init', array($this, 'maybe_schedule'));
    }

    public function register_weekly(array $schedules): array
    {
        if (! isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => 7 * DAY_IN_SECONDS,
                'display' => __('Once Weekly', 'default'),
            );
        }

        return $schedules;
    }

    public function maybe_schedule(): void
    {
        if (! $this->plugin->consent()->has_usage()) {
            // No consent → make sure nothing is scheduled.
            $this->unschedule();
            return;
        }

        if (! wp_next_scheduled($this->hook)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', $this->hook);
        }
    }

    public function run(): void
    {
        $this->plugin->client()->send_heartbeat();
    }

    public function unschedule(): void
    {
        $timestamp = wp_next_scheduled($this->hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->hook);
        }
    }
}
