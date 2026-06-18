<?php

namespace Shakvaro\WP\Insights;

use Shakvaro\WP\Insights\Collectors\Environment;
use Shakvaro\WP\Insights\Collectors\Features;
use Shakvaro\WP\Insights\Collectors\Identity;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds and sends telemetry pings. Always fail-silent: a dead or slow endpoint
 * never surfaces to the user or blocks a request.
 */
class Client
{
    public function __construct(protected Plugin $plugin) {}

    public function send_install(): void
    {
        if (! get_option($this->plugin->option_key('installed_at'))) {
            update_option($this->plugin->option_key('installed_at'), time(), false);
        }
        $this->send('install');
    }

    public function send_heartbeat(): void
    {
        $this->send('heartbeat');
    }

    public function send_activation(): void
    {
        $this->send('activation');
    }

    public function send_deactivation(string $reasonCode = '', string $reasonText = ''): void
    {
        $this->send('deactivation', array(
            'deactivation' => array(
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
            ),
        ), false); // blocking — we want it to leave before deactivation completes
    }

    public function send_update(string $fromVersion, string $toVersion): void
    {
        $this->send('update', array(
            'event' => array(
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
            ),
        ));
    }

    public function send_delete(string $reason = 'opt_out'): void
    {
        $payload = array(
            'install_uuid' => $this->plugin->uuid(),
            'plugin' => array('slug' => $this->plugin->slug()),
            'reason' => $reason,
        );

        $this->post('/v1/delete', $payload, false);
    }

    /**
     * Assemble + send a /v1/track ping for the given event type.
     */
    protected function send(string $type, array $extra = array(), bool $nonBlocking = true): void
    {
        $consent = $this->plugin->consent();

        // Hard gate: never send anything without usage consent.
        if (! $consent->has_usage()) {
            return;
        }

        $marketing = $consent->has_marketing();

        $payload = array(
            'install_uuid' => $this->plugin->uuid(),
            'event' => array(
                'id' => wp_generate_uuid4(),
                'type' => $type,
                'occurred_at' => gmdate('c'),
            ),
            'plugin' => array(
                'slug' => $this->plugin->slug(),
                'version' => $this->plugin->config('version'),
            ),
            'site' => array(
                'url_hash' => Identity::url_hash(),
                'title' => Identity::site_title(),
            ),
            'consent' => array(
                'usage' => true,
                'marketing' => $marketing,
            ),
            'environment' => Environment::collect(),
            'features' => Features::collect($this->plugin),
            'sdk_version' => defined('SHAKVARO_WP_INSIGHTS_BOOTED') ? SHAKVARO_WP_INSIGHTS_BOOTED : null,
        );

        if ($marketing) {
            $payload['identity'] = array('admin_email' => Identity::admin_email());
        }

        // Merge any extra event fields (e.g. from/to version) into the event
        // rather than replacing the whole event object.
        if (isset($extra['event']) && is_array($extra['event'])) {
            $payload['event'] = array_merge($payload['event'], $extra['event']);
            unset($extra['event']);
        }

        $payload = array_merge($payload, $extra);

        $this->post('/v1/track', $payload, $nonBlocking);
    }

    /**
     * Fire the HTTP request. Swallows every error.
     */
    protected function post(string $path, array $payload, bool $nonBlocking = true): void
    {
        try {
            $endpoint = rtrim((string) $this->plugin->config('endpoint'), '/');
            $body = wp_json_encode($payload);

            $args = array(
                // All outbound calls are tightly bounded so a slow/dead endpoint can
                // never hang wp-admin. Non-blocking (install/heartbeat) fire-and-forget
                // at 1s; blocking (deactivation/delete) wait briefly for the request to
                // leave at 1.5s. Every call is fail-silent regardless of the response.
                'timeout' => $nonBlocking ? 1 : 1.5,
                'blocking' => ! $nonBlocking,
                'redirection' => 0,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Shakvaro-Key' => (string) $this->plugin->config('api_key'),
                    'X-Shakvaro-Sign' => hash_hmac('sha256', $body, $this->signing_secret()),
                ),
                'body' => $body,
                'user-agent' => 'ShakvaroWPInsights/' . (defined('SHAKVARO_WP_INSIGHTS_BOOTED') ? SHAKVARO_WP_INSIGHTS_BOOTED : '1'),
            );

            wp_remote_post($endpoint . $path, $args);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ShakvaroWPInsights] send failed: ' . $e->getMessage()); // phpcs:ignore
            }
        }
    }

    /**
     * The HMAC signing secret.
     *
     * NOTE: the server verifies against the plugin's secret_salt. Since a public
     * key alone cannot hold a real secret in distributed code, the shared signing
     * value is the api_key itself unless a build injects a dedicated salt. The
     * server treats this as spam-deterrence + rate-limiting, not hard auth.
     */
    protected function signing_secret(): string
    {
        $salt = $this->plugin->config('signing_secret');

        return $salt ? (string) $salt : (string) $this->plugin->config('api_key');
    }
}
