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
 * @return bool
 */
function isWebRequest(): bool
{
    static $isWeb;
    if (is_bool($isWeb)) {
        return $isWeb;
    }
    if (defined('WP_CLI') && (\WP_CLI !== false)) {
        $isWeb = false;

        return false;
    }

    if (!class_exists(\Automattic\VIP\Utils\Context::class)) {
        $isWeb = false;

        return false;
    }

    $isWeb = \Automattic\VIP\Utils\Context::is_web_request()
        && !\Automattic\VIP\Utils\Context::is_healthcheck()
        && !\Automattic\VIP\Utils\Context::is_maintenance_mode();

    return $isWeb;
}

/**
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
        $env = \VIP_GO_APP_ENVIRONMENT;
    } elseif (defined('VIP_GO_ENV')) {
        $env = \VIP_GO_ENV;
    }
    if (!is_string($env) || ($env === '')) {
        $env = 'local';
    }

    return $env;
}

/**
 * @return "local"|"development"|"staging"|"production"
 */
function determineWpEnv(): string
{
    static $env;
    if (is_string($env)) {
        /** @var "local"|"development"|"staging"|"production" $env */
        return $env;
    }

    $configEnv = determineVipEnv();

    if (isset(ENV_NORMALIZATION_MAP[$configEnv])) {
        $env = ENV_NORMALIZATION_MAP[$configEnv];

        return $env;
    }

    $initials = [
        'local' => 'local',
        'dev' => 'development',
        'prod' => 'production',
        'live' => 'production',
        'public' => 'production',
    ];

    $env = 'staging';
    foreach ($initials as $initial => $target) {
        if (str_starts_with($configEnv, $initial)) {
            $env = $target;
            break;
        }
    }

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
 * @return string
 */
function privateDirPath(): string
{
    $fallback = dirname(__DIR__) . '/private';

    if (!isLocalEnv()) {
        return defined('WPCOM_VIP_PRIVATE_DIR') ? (string) \WPCOM_VIP_PRIVATE_DIR : $fallback;
    }

    return $fallback;
}

/**
 * @param string $location
 * @param int $status
 */
function earlyRedirect(string $location, int $status = 301): void
{
    if (headers_sent()) {
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
        $scheme = 'https';
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

    $urlSafe = filter_var($url, FILTER_SANITIZE_URL);
    if ($urlSafe !== '') {
        return $urlSafe;
    }

    return null;
}

/**
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

    if (($deployId === null) || ($deployId === '') || ($deployId === '0')) {
        $deployId = isLocalEnv() ? bin2hex(random_bytes(8)) : null;
    }

    /** @var non-falsy-string|null $deployId */
    return $deployId;
}

/**
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
 * @return array
 */
function loadSunriseConfig(): array
{
    static $domains;
    if (is_array($domains)) {
        return $domains;
    }

    $domains = [];

    $sunriseConfig = null;
    if (file_exists(__DIR__ . '/sunrise-config.php')) {
        $sunriseConfig = include __DIR__ . '/sunrise-config.php';
    } elseif (file_exists(__DIR__ . '/sunrise-config.json')) {
        $data = (string) file_get_contents(__DIR__ . '/sunrise-config.json');
        $sunriseConfig = json_decode($data, true);
    }

    if (is_array($sunriseConfig)) {
        $vipEnv = determineVipEnv();
        $wpEnv = determineWpEnv();
        $sunriseConfig = $sunriseConfig[$vipEnv] ?? $sunriseConfig[$wpEnv] ?? $sunriseConfig;
        is_array($sunriseConfig) and $domains = $sunriseConfig;
    }

    return $domains;
}

/**
 * @param string $domain
 * @return array{'target': string, 'redirect': bool, 'preservePath': bool, 'preserveQuery': bool}
 */
function loadSunriseConfigForDomain(string $domain): array
{
    $targetData = loadSunriseConfig()[$domain] ?? null;
    if (is_string($targetData)) {
        $targetData = [
            'target' => $targetData,
            'redirect' => true,
            'preservePath' => true,
            'preserveQuery' => true,
        ];
    }
    if (!is_array($targetData)) {
        return [
            'target' => '',
            'redirect' => false,
            'preservePath' => false,
            'preserveQuery' => false,
        ];
    }

    $target = $targetData['target'] ?? null;
    if (($target === '') || !is_string($target)) {
        return [
            'target' => '',
            'redirect' => false,
            'preservePath' => false,
            'preserveQuery' => false,
        ];
    }

    $redirect = (bool) ($targetData['redirect'] ?? false);
    $preservePath = (bool) ($targetData['preservePath'] ?? false);
    $preserveQuery = (bool) ($targetData['preserveQuery'] ?? false);

    return compact('target', 'redirect', 'preservePath', 'preserveQuery');
}

/**
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
 * @return bool
 */
function isAutotestRequest(): bool
{
    static $is;
    if (!isset($is)) {
        require_once __DIR__ . ' /__autotest-checker.php';
        $checker = new AutotestChecker();
        $is = $checker->isAutotestRequest();
    }
    /** @var bool $is */
    return $is;
}
