<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * "Why are you deactivating?" survey on the Plugins screen. The actual modal is
 * driven by assets/insights.js; the reason is POSTed back via admin-ajax and
 * forwarded to the backend. Deactivation is never blocked.
 */
class DeactivationModal
{
    public function __construct(protected Plugin $plugin) {}

    protected function ajax_action(): string
    {
        // Per-plugin action name. A SHARED action name would make every plugin's
        // handler run for one request; the first to fail its own nonce check would
        // wp_send_json_error() (which dies) before the right plugin handled it.
        return 'shakvaro_insights_deactivate_' . md5($this->plugin->slug());
    }

    public function init(): void
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_footer-plugins.php', array($this, 'render_modal'));
        add_action('wp_ajax_' . $this->ajax_action(), array($this, 'handle_ajax'));
    }

    public function enqueue(string $hook): void
    {
        if ('plugins.php' !== $hook || ! $this->plugin->consent()->has_usage()) {
            return;
        }

        $url = Loader::url();
        wp_enqueue_style('shakvaro-insights', $url . '/assets/insights.css', array(), SHAKVARO_WP_INSIGHTS_BOOTED);
        wp_enqueue_script('shakvaro-insights', $url . '/assets/insights.js', array('jquery'), SHAKVARO_WP_INSIGHTS_BOOTED, true);

        wp_localize_script('shakvaro-insights', 'ShakvaroInsights_' . md5($this->plugin->slug()), array(
            'slug' => $this->plugin->slug(),
            'basename' => $this->plugin->config('plugin_file') ? plugin_basename($this->plugin->config('plugin_file')) : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => $this->ajax_action(),
            'nonce' => wp_create_nonce('shakvaro_insights_deactivate_' . $this->plugin->slug()),
            'modalId' => 'shakvaro-deactivate-' . md5($this->plugin->slug()),
        ));
    }

    public function render_modal(): void
    {
        if (! $this->plugin->consent()->has_usage()) {
            return;
        }
        $id = 'shakvaro-deactivate-' . md5($this->plugin->slug());
        $name = esc_html($this->plugin->config('name'));
        ?>
        <div class="shakvaro-modal" id="<?php echo esc_attr($id); ?>" style="display:none;">
            <div class="shakvaro-modal__box">
                <h3><?php
                    /* translators: %s: plugin name */
                    echo esc_html(sprintf(__('Quick question before you deactivate %s', 'default'), $name));
                ?></h3>
                <?php
                $reasons = array(
                    'temporary' => __('It is temporary — I will be back', 'default'),
                    'better_plugin' => __('I found a better plugin', 'default'),
                    'not_needed' => __('I no longer need it', 'default'),
                    'broke_site' => __('It broke my site or did not work', 'default'),
                    'other' => __('Other', 'default'),
                );
                foreach ($reasons as $code => $label) :
                    ?>
                    <label class="shakvaro-modal__reason">
                        <input type="radio" name="shakvaro_reason" value="<?php echo esc_attr($code); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                <textarea class="shakvaro-modal__text" placeholder="<?php echo esc_attr__('Tell us more (optional)', 'default'); ?>"></textarea>
                <div class="shakvaro-modal__actions">
                    <button class="button button-primary shakvaro-modal__submit"><?php echo esc_html__('Submit & Deactivate', 'default'); ?></button>
                    <button class="button shakvaro-modal__skip"><?php echo esc_html__('Skip & Deactivate', 'default'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_ajax(): void
    {
        if (! current_user_can('activate_plugins')
            || ! isset($_POST['nonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shakvaro_insights_deactivate_' . $this->plugin->slug())) {
            wp_send_json_error();
        }

        $code = isset($_POST['reason_code']) ? sanitize_text_field(wp_unslash($_POST['reason_code'])) : '';
        $text = isset($_POST['reason_text']) ? sanitize_textarea_field(wp_unslash($_POST['reason_text'])) : '';

        $this->plugin->client()->send_deactivation($code, $text);

        wp_send_json_success();
    }
}
