<?php

declare(strict_types=1);

namespace Inpsyde\Vip;

const REDIRECT_STATUS_CODES = [
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    306 => 'Reserved',
    307 => 'Temporary Redirect',
    308 => 'Permanent Redirect',
];

const ENV_NORMALIZATION_MAP = [
    'local' => 'local',
    'development' => 'development',
    'dev' => 'development',
    'develop' => 'development',
    'staging' => 'staging',
    'stage' => 'staging',
    'pre' => 'staging',
    'preprod' => 'staging',
    'pre-prod' => 'staging',
    'pre-production' => 'staging',
    'preproduction' => 'staging',
    'test' => 'staging',
    'tests' => 'staging',
    'testing' => 'staging',
    'uat' => 'staging',
    'qa' => 'staging',
    'acceptance' => 'staging',
    'accept' => 'staging',
    'production' => 'production',
    'prod' => 'production',
    'live' => 'production',
    'public' => 'production',
];

/**
 * Returns true when a request is a "regular user request" from the web.
 *
 * It excludes CLI, REST, AJAX, XML-RPC, health check and other system requests, and requests in
 * maintenance mode.
 * These latter checks are dove via VIP Context class, and if that is nto available then we assume
 * is not a web request either.
 *
 * @return bool
 *
 * @see https://github.com/Automattic/vip-go-mu-plugins-built/blob/master/lib/utils/class-context.php
 */
function isWebRequest(): bool
{
    static $isWeb;
    if (is_bool($isWeb)) {
        return $isWeb;
    }
    $isWeb = false;
    /** @psalm-suppress UndefinedConstant */
    if (defined('WP_CLI') && \WP_CLI) {
        return false;
    }

    if (!class_exists(\Automattic\VIP\Utils\Context::class)) {
        return false;
    }

    $isWeb = \Automattic\VIP\Utils\Context::is_web_request()
        && !\Automattic\VIP\Utils\Context::is_healthcheck()
        && !\Automattic\VIP\Utils\Context::is_maintenance_mode()
        && !\Automattic\VIP\Utils\Context::is_prom_endpoint_request();

    return $isWeb;
}

/**
 * Determine the environment name of the VIP application.
 *
 * This is normally based on the VIP repository branch, with the only exclusions of "production"
 * environment be based on the "master" branch.
 *
 * @return non-empty-string
 */
function determineVipEnv(): string
{
    static $env;
    if (is_string($env)) {
        /** @var non-empty-string $env */
        return $env;
    }

    $env = null;
    if (defined('VIP_GO_APP_ENVIRONMENT')) {
        // This is defined also in VIP local development environment based on Lando, when is "local"
        $env = \VIP_GO_APP_ENVIRONMENT;
    } elseif (defined('VIP_GO_ENV')) {
        // This is only defined in "online" VIP environments
        $env = \VIP_GO_ENV;
    }
    // Fallback to local, because that means is not "online"
    if (!is_string($env) || ($env === '')) {
        $env = 'local';
    }

    return $env;
}

/**
 * Similar to `wp_get_environment_type()`, it returns one of the four values supported by that
 * function, but this can be called earlier, and it is based on VIP environment.
 *
 * The first time this is called it also defines the `WP_ENVIRONMENT_TYPE` constant, ensuring that
 * `wp_get_environment_type()` will return the same value.
 *
 * @return "local"|"development"|"staging"|"production"
 *
 * @see https://developer.wordpress.org/reference/functions/wp_get_environment_type/
 */
function determineWpEnv(): string
{
    static $env;
    if (is_string($env)) {
        /** @var "local"|"development"|"staging"|"production" $env */
        return $env;
    }

    /*
     * If the constant is already defined when we call the function for the first time, then we
     * can't do anything more, because the ultimate goal is to use this function to auto-determine
     * the WP environment. The fallback is "production" because that is what WordPress is going to
     * use anyway if the value of the constant is not one of four supported ones.
    */
    if (defined('WP_ENVIRONMENT_TYPE')) {
        /** @var "local"|"development"|"staging"|"production" $type */
        $type = in_array(\WP_ENVIRONMENT_TYPE, ['local', 'development', 'staging'], true)
            ? \WP_ENVIRONMENT_TYPE
            : 'production';

        return $type;
    }

    $vipEnv = determineVipEnv();

    if (isset(ENV_NORMALIZATION_MAP[$vipEnv])) {
        $env = ENV_NORMALIZATION_MAP[$vipEnv];
        define('WP_ENVIRONMENT_TYPE', $env);

        return $env;
    }

    $initials = [
        'local' => 'local',
        'dev' => 'development',
        'prod' => 'production',
        'live' => 'production',
        'public' => 'production',
    ];

    // When auto-determining the environment we fall back to "staging" because it is "safer" and
    // chances are that a non-standard VIP environment name is not used for production.
    $env = 'staging';
    foreach ($initials as $initial => $target) {
        if (str_starts_with($vipEnv, $initial)) {
            $env = $target;
            break;
        }
    }
    define('WP_ENVIRONMENT_TYPE', $env);

    return $env;
}

/**
 * @return bool
 */
function isLocalEnv(): bool
{
    return determineWpEnv() === 'local';
}

/**
 * @return bool
 */
function isProdEnv(): bool
{
    return determineWpEnv() === 'production';
}

/**
 * The path of the `/private` directory, with fallback for local environments.
 *
 * @return string
 */
function privateDirPath(): string
{
    $fallback = dirname(__DIR__) . '/private';

    return defined('WPCOM_VIP_PRIVATE_DIR') ? (string) \WPCOM_VIP_PRIVATE_DIR : $fallback;
}

/**
 * The path of the `/vip-config` directory.
 *
 * @return string
 */
function vipConfigPath(): string
{
    return __DIR__;
}

/**
 * Similar to `wp_redirect` but can be called before WP is loaded, ands sets `X-Redirect-By` header.
 *
 * @param string $location
 * @param int $status
 */
function earlyRedirect(string $location, int $status = 301): void
{
    if (headers_sent()) {
        return;
    }

    $location = filter_var($location, FILTER_VALIDATE_URL)
        ? filter_var($location, FILTER_SANITIZE_URL)
        : '';
    if ($location === '') {
        return;
    }

    isset(REDIRECT_STATUS_CODES[$status]) or $status = 301;

    if (empty($GLOBALS['is_IIS']) && (PHP_SAPI !== 'cgi-fcgi')) {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
        if (!in_array($protocol, ['HTTP/1.0', 'HTTP/2', 'HTTP/2.0', 'HTTP/3'], true)) {
            $protocol = 'HTTP/1.1';
        }
        $statusHeader = sprintf('%s %d %s', $protocol, $status, REDIRECT_STATUS_CODES[$status]);
        header($statusHeader, true, $status);
    }
    header('X-Redirect-By: ' . strtoupper(str_replace('\\', '-', __FUNCTION__)));
    header("Location: {$location}", true, $status);
    exit;
}

/**
 * Similar to `is_ssl` but can be called before WP is loaded.
 *
 * @return bool
 */
function isSsl(): bool
{
    if (function_exists('is_ssl')) {
        return (bool) \is_ssl();
    }

    return filter_var($_SERVER['HTTPS'] ?? false, FILTER_VALIDATE_BOOLEAN)
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

/**
 * @param string $base
 * @param bool $preservePath
 * @param bool $preserveQuery
 * @return non-empty-string|null
 */
function buildFullRedirectUrlFor(
    string $base,
    bool $preservePath = true,
    bool $preserveQuery = true
): ?string {

    if ($base === '') {
        return null;
    }

    $query = null;
    $url = $base;

    if (!str_starts_with($url, 'http:') && !str_starts_with($url, 'https:')) {
        $scheme = isSsl() ? 'https:' : 'http:';
        str_starts_with($url, '//') or $scheme .= '//';
        $url = $scheme . $url;
    }

    if ($preserveQuery) {
        /** @var non-empty-string $url */
        $url = strtok($url, '?');
        $query = strtok('?');
    }

    if ($preservePath) {
        $path = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $url = rtrim($url, '/') . $path;
    }

    /** @psalm-suppress RedundantCondition */
    if ($preserveQuery && ($_GET !== []) && is_array($_GET)) {
        $baseVars = [];
        is_string($query) and parse_str($query, $baseVars);
        $query = http_build_query(array_merge($_GET, $baseVars));
    }

    if (($query !== '') && is_string($query)) {
        $url .= "?{$query}";
    }

    return $url;
}

/**
 * The path of the "deploy-id" file generated by `composer vip` command. Null if not found.
 *
 * @return non-falsy-string|null
 */
function deployIdFile(): ?string
{
    static $deployFile, $deployFileChecked;
    if ($deployFileChecked) {
        /** @var non-falsy-string|null $deployFile */
        return $deployFile;
    }

    $deployFileChecked = true;
    $deployFile = null;

    $privateDir = privateDirPath();
    if (file_exists("{$privateDir}/deploy-id") && is_readable("{$privateDir}/deploy-id")) {
        /** @var non-falsy-string $deployFile */
        $deployFile = "{$privateDir}/deploy-id";

        return $deployFile;
    }

    return null;
}

/**
 * The content of "deploy-id" file generated by `composer vip` command.
 *
 * Excluding production, a random string returned if the file is not found. Reason is this is used
 * to invalidate cache and so it make sense to have a changing value when not in production.
 *
 * @return non-falsy-string|null
 */
function deployId(): ?string
{
    static $deployId, $deployIdChecked;
    if ($deployIdChecked) {
        /** @var non-falsy-string|null $deployId */
        return $deployId;
    }

    $deployIdChecked = true;
    $deployId = null;

    $deployIdFile = deployIdFile();
    ($deployIdFile) and $deployId = trim((string) @file_get_contents($deployIdFile));

    if (!((bool) $deployId)) {
        $deployId = isProdEnv() ? null : bin2hex(random_bytes(8));
    }

    /** @var non-falsy-string|null $deployId */
    return $deployId;
}

/**
 * The content of "deploy-ver" file generated by `composer vip` command. Null if file not found.
 *
 * @return non-empty-string|null
 */
function deployVersion(): ?string
{
    static $deployVer, $deployVerChecked;
    if ($deployVerChecked) {
        /** @var non-empty-string|null $deployVer */
        return $deployVer;
    }

    $deployVerChecked = true;
    $deployVer = null;
    $privateDir = privateDirPath();

    if (file_exists("{$privateDir}/deploy-ver")) {
        $deployVer = trim((string) @file_get_contents("{$privateDir}/deploy-ver"));
        ($deployVer === '') and $deployVer = null;
    }
    /** @var non-empty-string|null $deployVer */
    return $deployVer;
}

/**
 * Load the configuration for redirections and domain mapping for a specific domain.
 *
 * @param string $domain
 * @return array{'target':string, 'redirect':bool, 'preservePath':bool, 'preserveQuery':bool}
 */
function loadSunriseConfigForDomain(string $domain): array
{
    static $loader;
    if (!isset($loader)) {
        require_once __DIR__ . '/__sunrise-config-loader.php';
        $loader = new SunriseConfigLoader(vipConfigPath());
    }

    return $loader->loadForDomain($domain);
}

/**
 * Loads env-specific configuration files for the `vip-config/env` folder.
 *
 * @return void
 */
function loadConfigFiles(): void
{
    static $loaded;
    if ($loaded) {
        return;
    }

    $loaded = true;
    $wpEnv = determineWpEnv();
    $vipEnv = determineVipEnv();
    $configPath = __DIR__ . '/env';
    $paths = ["{$configPath}/{$vipEnv}.php"];
    ($wpEnv !== $vipEnv) and $paths[] = "{$configPath}/{$wpEnv}.php";
    isLocalEnv() and $paths[] = "{$configPath}/development.php";

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }

    if (file_exists("{$configPath}/all.php")) {
        require_once "{$configPath}/all.php";
    }
}

/**
 * Use the `AutotestChecker` class to set the `WP_RUN_CORE_TESTS` constants which prevents 2FA to
 * be loaded, and thus facilitate automated tests.
 *
 * A request is recognized as an "automated tests" request thanks to a secret being present in
 * globals, HTTP headers or cookies.
 *
 * @return void
 */
function skip2faForAutotestRequest(): void
{
    static $done;
    if (!isset($done)) {
        $done = true;
        require_once __DIR__ . ' /__automated-test-checker.php';
        (new AutomatedTestChecker())->maybeSkip2fa();
    }
}
