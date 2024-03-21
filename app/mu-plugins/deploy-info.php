<?php

/**
 * Plugin Name: Deploy information in WP dashboard footer.
 */

declare(strict_types=1);

namespace Inpsyde\Vip;

if (
    !function_exists(__NAMESPACE__ . '\\deployVersion')
    || !function_exists(__NAMESPACE__ . '\\deployId')
    || !function_exists(__NAMESPACE__ . '\\deployIdFile')
    || !function_exists(__NAMESPACE__ . '\\determineWpEnv')
    || !function_exists(__NAMESPACE__ . '\\determineVipEnv')
) {
    return;
}

add_filter(
    'admin_footer_text',
    static function (mixed $text): mixed {
        $version = deployVersion();
        $id = deployId();
        $deployIdFile = deployIdFile();
        $timestamp = $deployIdFile ? @filemtime($deployIdFile) : null;
        $datetime = is_int($timestamp) ? date('Y-m-d H:i \U\T\C', $timestamp) : null;

        if (($version === null) && ($id === null) && ($datetime === null)) {
            return $text;
        }

        ob_start();
        is_string($text) and print "{$text}";
        ?>
        </span>
        <br><br>
        <span id="deployment-info">
            <strong style="font-variant:small-caps">Deployment Info</strong>
            <em>Version:</em>&nbsp;<strong><?= esc_html($version ?? 'n/a') ?></strong>
            | <em>ID:</em>&nbsp;<strong><?= esc_html($id ?? 'n/a') ?></strong>
            | <em>Date/Time</em>&nbsp;<strong><?= esc_html($datetime ?? 'n/a') ?></strong>
            | <em>WP Env</em>&nbsp;<strong><?= esc_html(determineWpEnv()) ?></strong>
            | <em>VIP Env</em>&nbsp;<strong><?= esc_html(determineVipEnv()) ?></strong>
        </span>
        <span>
        <?php
        return (string) ob_get_clean();
    },
    PHP_INT_MAX - 1
);
