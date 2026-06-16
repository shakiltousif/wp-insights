<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Two-tier opt-in consent. OFF by default. Nothing is sent until the user ticks
 * a box and clicks Allow. Declining ("Skip") leaves the plugin fully functional.
 */
class Consent
{
    public function __construct(protected Plugin $plugin) {}

    public function init(): void
    {
        add_action('admin_notices', array($this, 'render_notice'));
        add_action('admin_post_shakvaro_insights_consent', array($this, 'handle_submit'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    protected function state(): array
    {
        $state = get_option($this->plugin->option_key('consent'));

        return is_array($state) ? $state : array();
    }

    public function decided(): bool
    {
        return ! empty($this->state()['decided_at']);
    }

    public function has_usage(): bool
    {
        return ! empty($this->state()['usage']);
    }

    public function has_marketing(): bool
    {
        return ! empty($this->state()['marketing']);
    }

    /**
     * Store consent and (on first usage opt-in) fire the install ping.
     */
    public function store(bool $usage, bool $marketing): void
    {
        $wasUsage = $this->has_usage();

        update_option($this->plugin->option_key('consent'), array(
            'usage' => $usage,
            'marketing' => $marketing,
            'decided_at' => time(),
            'sdk_version' => defined('SHAKVARO_WP_INSIGHTS_BOOTED') ? SHAKVARO_WP_INSIGHTS_BOOTED : '1',
        ), false);

        if ($usage && ! $wasUsage) {
            $this->plugin->client()->send_install();
        }

        if (! $usage && $wasUsage) {
            // Withdrew usage consent → request deletion.
            $this->plugin->client()->send_delete('opt_out');
        }
    }

    public function enqueue_assets(): void
    {
        if ($this->decided()) {
            return;
        }
        $url = Loader::url();
        wp_enqueue_style('shakvaro-insights', $url . '/assets/insights.css', array(), SHAKVARO_WP_INSIGHTS_BOOTED);
    }

    /**
     * Render the opt-in admin notice (only until a decision is made).
     */
    public function render_notice(): void
    {
        if ($this->decided() || ! current_user_can('manage_options')) {
            return;
        }

        $name = esc_html($this->plugin->config('name'));
        $action = esc_url(admin_url('admin-post.php'));
        $nonce = wp_create_nonce('shakvaro_insights_consent_' . $this->plugin->slug());
        $slug = esc_attr($this->plugin->slug());
        ?>
        <div class="notice notice-info shakvaro-insights-notice">
            <form method="post" action="<?php echo $action; ?>">
                <input type="hidden" name="action" value="shakvaro_insights_consent">
                <input type="hidden" name="slug" value="<?php echo $slug; ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                <p>
                    <strong><?php
                        /* translators: %s: plugin name */
                        echo esc_html(sprintf(__('Help improve %s', 'default'), $name));
                    ?></strong>
                </p>
                <p><?php echo esc_html__('Allow this plugin to send non-sensitive diagnostics (WordPress/PHP/WooCommerce versions, plugin version, which features you use, a one-way hash of your site URL) so we can fix bugs and prioritize features. This is optional and off by default.', 'default'); ?></p>
                <p>
                    <label><input type="checkbox" name="usage" value="1"> <?php echo esc_html__('Share usage & diagnostic data', 'default'); ?></label><br>
                    <label><input type="checkbox" name="marketing" value="1"> <?php echo esc_html__('Also email me important product updates (uses your admin email)', 'default'); ?></label>
                </p>
                <p>
                    <button type="submit" name="decision" value="allow" class="button button-primary"><?php echo esc_html__('Allow selected', 'default'); ?></button>
                    <button type="submit" name="decision" value="skip" class="button"><?php echo esc_html__('Skip', 'default'); ?></button>
                    <a href="<?php echo esc_url($this->plugin->config('privacy_url')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Privacy Policy', 'default'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the consent form submission.
     */
    public function handle_submit(): void
    {
        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

        // Only the plugin instance matching the submitted slug acts.
        if ($slug !== $this->plugin->slug()) {
            return;
        }

        if (! current_user_can('manage_options')
            || ! isset($_POST['_wpnonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'shakvaro_insights_consent_' . $slug)) {
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }

        $decision = isset($_POST['decision']) ? sanitize_text_field(wp_unslash($_POST['decision'])) : 'skip';

        if ('allow' === $decision) {
            $usage = ! empty($_POST['usage']);
            $marketing = ! empty($_POST['marketing']);
            $this->store($usage, $marketing);
        } else {
            $this->store(false, false);
        }

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
