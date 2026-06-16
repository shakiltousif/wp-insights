<?php

namespace Shakvaro\WP\Insights\Collectors;

use Shakvaro\WP\Insights\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Evaluates the plugin-declared feature callbacks into a flat state map.
 */
class Features
{
    public static function collect(Plugin $plugin): array
    {
        $state = array();

        foreach ($plugin->feature_callbacks() as $key => $callable) {
            try {
                $value = call_user_func($callable);
                // Normalize to scalar (bool/string/int).
                if (is_bool($value) || is_string($value) || is_int($value)) {
                    $state[$key] = $value;
                } else {
                    $state[$key] = (bool) $value;
                }
            } catch (\Throwable $e) {
                // A misbehaving feature callback must never break telemetry.
                $state[$key] = null;
            }
        }

        return $state;
    }
}
