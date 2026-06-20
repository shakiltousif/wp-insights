<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-plugin controller. Owns the registration config, feature callbacks, and
 * the consent/client/scheduler wiring for one host plugin.
 */
class Plugin
{
    protected array $config;

    /** @var array<string, callable> feature key => callable returning bool|string */
    protected array $features = array();

    protected bool $deactivationSurvey = false;

    protected Consent $consent;

    protected Client $client;

    public function __construct(array $config)
    {
        $this->config = array_merge(array(
            'slug' => 'unknown',
            'name' => 'Plugin',
            'version' => '1.0.0',
            'plugin_file' => '',
            'api_key' => '',
            'endpoint' => 'https://track.shakvaro.cloud',
            'privacy_url' => 'https://shakvaro.com/wp-insights/privacy',
            'textdomain' => 'default',
        ), $config);

        $this->consent = new Consent($this);
        $this->client = new Client($this);
    }

    public function boot(): void
    {
        $this->consent->init();
        (new Scheduler($this))->init();

        if ($this->deactivationSurvey) {
            (new DeactivationModal($this))->init();
        }

        (new Uninstall($this))->init();

        add_action('admin_init', array($this, 'maybe_send_activation'));
        add_action('admin_init', array($this, 'maybe_track_version'));
    }

    /**
     * Send an `activation` ping if mark_activated() was called during the host's
     * activation request (which runs before the SDK boots). Fires once per
     * (re)activation, consent-gated, fail-silent.
     */
    public function maybe_send_activation(): void
    {
        $key = 'shakvaro_insights_pending_activation_' . md5($this->slug());
        if (! get_option($key)) {
            return;
        }
        delete_option($key);

        if ($this->consent->has_usage()) {
            $this->client->send_activation();
        }
    }

    /**
     * Detect a plugin version change between loads and report it as an `update`
     * event (only while usage consent holds).
     */
    public function maybe_track_version(): void
    {
        if (! $this->consent->has_usage()) {
            return;
        }

        $key = $this->option_key('last_version');
        $current = (string) $this->config('version');
        $stored = get_option($key, '');

        if ('' === $stored) {
            // First time we see a version after consent — record, don't report.
            update_option($key, $current, false);
            return;
        }

        if ($stored !== $current) {
            $this->client->send_update($stored, $current);
            update_option($key, $current, false);
        }
    }

    // ----- Public fluent API -----

    public function track_feature(string $key, callable $callable): self
    {
        $this->features[$key] = $callable;

        return $this;
    }

    public function add_deactivation_survey(): self
    {
        $this->deactivationSurvey = true;

        return $this;
    }

    public function set_textdomain(string $domain): self
    {
        $this->config['textdomain'] = $domain;

        return $this;
    }

    // ----- Accessors -----

    public function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function slug(): string
    {
        return (string) $this->config['slug'];
    }

    public function consent(): Consent
    {
        return $this->consent;
    }

    public function client(): Client
    {
        return $this->client;
    }

    /** @return array<string, callable> */
    public function feature_callbacks(): array
    {
        return $this->features;
    }

    /**
     * Option key namespaced per plugin slug.
     */
    public function option_key(string $suffix): string
    {
        return 'shakvaro_insights_' . $suffix . '_' . md5($this->slug());
    }

    /**
     * Stable per-install UUID (created once, stored in options).
     */
    public function uuid(): string
    {
        $key = $this->option_key('uuid');
        $uuid = get_option($key);

        if (! $uuid) {
            $uuid = wp_generate_uuid4();
            update_option($key, $uuid, false);
        }

        return $uuid;
    }
}
