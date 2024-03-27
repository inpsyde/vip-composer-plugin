<?php

declare(strict_types=1);

/*
 * Helpers functions used in this file.
 */
require_once __DIR__ . '/__helpers.php';

/*
 * Custom configuration files that must be loaded as soon as possible.
 * These files allow us to early define any constant defined in this file, and thus override the
 * default behavior.
 */
if (file_exists(__DIR__ . '/vip-config.override.php')) {
    require_once __DIR__ . '/vip-config.override.php';
}
$wpEnv = Inpsyde\Vip\determineWpEnv();
$vipEnv = Inpsyde\Vip\determineVipEnv();
if (file_exists(__DIR__ . "/vip-config.{$vipEnv}.php")) {
    require_once __DIR__ . "/vip-config.{$vipEnv}.php";
}
if (($vipEnv !== $wpEnv) && file_exists(__DIR__ . "/vip-config.{$wpEnv}.php")) {
    require_once __DIR__ . "/vip-config.{$wpEnv}.php";
}
unset($vipEnv);

/*
 * Default environment-specific constants.
 */
switch ($wpEnv) {
    case 'local':
        defined('WP_LOCAL_DEV') or define('WP_LOCAL_DEV', true);
        defined('WPCOM_VIP_JETPACK_LOCAL') or define('WPCOM_VIP_JETPACK_LOCAL', true);
        // activate memcached
        defined('WP_CACHE') or define('WP_CACHE', true);
        $GLOBALS['memcached_servers'] = ['default' => ['memcached', '11211']];
        // fallback
    case 'development':
        defined('WP_DEBUG') or define('WP_DEBUG', true);
        defined('WP_DEBUG_LOG') or define('WP_DEBUG_LOG', true);
        defined('SAVEQUERIES') or define('SAVEQUERIES', true);
        defined('SCRIPT_DEBUG') or define('SCRIPT_DEBUG', true);
        defined('WP_DEBUG_DISPLAY') or define('WP_DEBUG_DISPLAY', true);
        defined('WP_DISABLE_FATAL_ERROR_HANDLER') or define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
        break;
    case 'staging':
        defined('WP_DEBUG') or define('WP_DEBUG', false);
        defined('WP_DEBUG_LOG') or define('WP_DEBUG_LOG', true);
        defined('SAVEQUERIES') or define('SAVEQUERIES', false);
        defined('SCRIPT_DEBUG') or define('SCRIPT_DEBUG', false);
        defined('WP_DEBUG_DISPLAY') or define('WP_DEBUG_DISPLAY', false);
        break;
    default:
        defined('WP_DEBUG') or define('WP_DEBUG', false);
        defined('WP_DEBUG_LOG') or define('WP_DEBUG_LOG', false);
        defined('SAVEQUERIES') or define('SAVEQUERIES', false);
        defined('SCRIPT_DEBUG') or define('SCRIPT_DEBUG', false);
        defined('WP_DEBUG_DISPLAY') or define('WP_DEBUG_DISPLAY', false);
        break;
}
unset($wpEnv);

/** @psalm-suppress RedundantCondition */
if ((defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE) || (defined('MULTISITE') && MULTISITE)) {
    $domain = Inpsyde\Vip\currentUrlHost();
    defined('MULTISITE') or define('MULTISITE', true);
    defined('SUBDOMAIN_INSTALL') or define('SUBDOMAIN_INSTALL', false);
    defined('DOMAIN_CURRENT_SITE') or define('DOMAIN_CURRENT_SITE', $domain);
    defined('PATH_CURRENT_SITE') or define('PATH_CURRENT_SITE', '/');
    defined('SITE_ID_CURRENT_SITE') or define('SITE_ID_CURRENT_SITE', 1);
    defined('BLOG_ID_CURRENT_SITE') or define('BLOG_ID_CURRENT_SITE', 1);
    defined('COOKIE_DOMAIN') or define('COOKIE_DOMAIN', ".{$domain}");
    defined('ADMIN_COOKIE_PATH') or define('ADMIN_COOKIE_PATH', '/');
    defined('COOKIEPATH') or define('COOKIEPATH', '/');
    defined('SITECOOKIEPATH') or define('SITECOOKIEPATH', '/');
    unset($domain);
}

/*
 * Some other generic WP constants that might be too late to define in MU plugin.
 *
 * phpcs:disable Inpsyde.CodeQuality.NoTopLevelDefine
 */
defined('DISALLOW_FILE_EDIT') or define('DISALLOW_FILE_EDIT', true);
defined('DISALLOW_FILE_MODS') or define('DISALLOW_FILE_MODS', true);
defined('AUTOMATIC_UPDATER_DISABLED') or define('AUTOMATIC_UPDATER_DISABLED', true);
defined('VIP_JETPACK_IS_PRIVATE') or define('VIP_JETPACK_IS_PRIVATE', true);

/*
 * Bypass 2FA for automated E2E tests requests.
 */
Inpsyde\Vip\skip2faForAutotestRequest();
