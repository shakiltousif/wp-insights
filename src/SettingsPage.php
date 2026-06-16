<?php

namespace Shakvaro\WP\Insights;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A single shared "Data Sharing" settings page (under Settings) listing every
 * registered Shakvaro plugin with a toggle for usage + marketing consent. This
 * is the discoverable opt-in / opt-out control required for compliance.
 *
 * Registered once for the whole site (not per plugin).
 */
class SettingsPage
{
    protected static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action('admin_menu', array(__CLASS__, 'add_page'));
        add_action('admin_post_shakvaro_insights_settings', array(__CLASS__, 'handle'));
    }

    public static function add_page(): void
    {
        if (empty(Insights::plugins())) {
            return;
        }

        add_submenu_page(
            'options-general.php',
            __('Shakvaro Data Sharing', 'default'),
            __('Data Sharing', 'default'),
            'manage_options',
            'shakvaro-insights-data-sharing',
            array(__CLASS__, 'render')
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Data Sharing', 'default'); ?></h1>
            <?php
            // Use the privacy URL of the first registered plugin (they share the page).
            $first = current(Insights::plugins());
            $privacy_url = $first ? $first->config('privacy_url') : 'https://shakvaro.com/wp-insights/privacy';
            ?>
            <p><?php echo esc_html__('Control what each plugin shares with the developer. Turning data sharing off stops all collection and requests deletion of previously collected data.', 'default'); ?>
                <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Privacy Policy', 'default'); ?></a>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="shakvaro_insights_settings">
                <?php wp_nonce_field('shakvaro_insights_settings'); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Plugin', 'default'); ?></th>
                            <th><?php echo esc_html__('Share usage & diagnostics', 'default'); ?></th>
                            <th><?php echo esc_html__('Product update emails', 'default'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (Insights::plugins() as $slug => $plugin) :
                            $consent = $plugin->consent(); ?>
                            <tr>
                                <td><strong><?php echo esc_html($plugin->config('name')); ?></strong></td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="usage[<?php echo esc_attr($slug); ?>]" value="1" <?php checked($consent->has_usage()); ?>>
                                        <?php echo esc_html__('Enabled', 'default'); ?>
                                    </label>
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="marketing[<?php echo esc_attr($slug); ?>]" value="1" <?php checked($consent->has_marketing()); ?>>
                                        <?php echo esc_html__('Enabled', 'default'); ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(__('Save changes', 'default')); ?>
            </form>
        </div>
        <?php
    }

    public static function handle(): void
    {
        if (! current_user_can('manage_options')
            || ! isset($_POST['_wpnonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'shakvaro_insights_settings')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }

        $usage = isset($_POST['usage']) && is_array($_POST['usage']) ? array_map('sanitize_text_field', wp_unslash($_POST['usage'])) : array();
        $marketing = isset($_POST['marketing']) && is_array($_POST['marketing']) ? array_map('sanitize_text_field', wp_unslash($_POST['marketing'])) : array();

        foreach (Insights::plugins() as $slug => $plugin) {
            $wantUsage = ! empty($usage[$slug]);
            $wantMarketing = ! empty($marketing[$slug]);

            // store() fires the install ping on a new usage opt-in and the delete
            // ping when usage consent is withdrawn.
            $plugin->consent()->store($wantUsage, $wantUsage ? $wantMarketing : false);
        }

        wp_safe_redirect(add_query_arg('updated', '1', wp_get_referer() ?: admin_url('options-general.php?page=shakvaro-insights-data-sharing')));
        exit;
    }
}
