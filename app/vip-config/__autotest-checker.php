<?php

declare(strict_types=1);

namespace Inpsyde\Vip;

// phpcs:disable Inpsyde.CodeQuality.Psr4

class AutotestChecker
{
    private const COOKIE_KEY = 'syde_autotest_key';

    /**
     * @return bool
     */
    public function isAutotestRequest(): bool
    {
        $secret = $this->autotestSecret();
        if ($secret === null) {
            return false;
        }

        return $this->isAutotestRequestByCookie($secret)
            || $this->isAutotestRequestByGlobals($secret)
            || $this->isAutotestRequestByHeader($secret);
    }

    /**
     * @return non-falsy-string|null
     */
    private function autotestSecret(): ?string
    {
        if (
            !defined('INPSYDE_AUTOTEST_KEY')
            || !is_string(\INPSYDE_AUTOTEST_KEY)
            || (\INPSYDE_AUTOTEST_KEY === '')
            || (\INPSYDE_AUTOTEST_KEY === '0')
            || defined('WP_RUN_CORE_TESTS')
            || (defined('WP_ENVIRONMENT_TYPE') && (WP_ENVIRONMENT_TYPE === 'production'))
        ) {
            return null;
        }
        /** @var non-falsy-string */
        return \INPSYDE_AUTOTEST_KEY;
    }

    /**
     * @param non-empty-string $secret
     * @return bool
     */
    private function isAutotestRequestByCookie(string $secret): bool
    {
        $cookieVal = $_COOKIE[self::COOKIE_KEY] ?? null;
        if (($cookieVal === null) || ($cookieVal === '')) {
            return false;
        }

        if (password_verify($secret, base64_decode($cookieVal))) {
            return true;
        }

        $this->saveCookie(null);

        return false;
    }

    /**
     * @param non-empty-string $secret
     * @return bool
     */
    private function isAutotestRequestByGlobals(string $secret): bool
    {
        $keyUp = strtoupper(self::COOKIE_KEY);
        $value = $_REQUEST[self::COOKIE_KEY] ?? $_REQUEST[$keyUp] ?? null;
        if (($value === '') || !is_string($value)) {
            return false;
        }

        if ($value === $secret) {
            return $this->saveCookie($secret);
        }

        return false;
    }

    /**
     * @param non-empty-string $secret
     * @return bool
     */
    private function isAutotestRequestByHeader(string $secret): bool
    {
        $headers = function_exists('getallheaders') ? getallheaders() : null;
        $keyUp = strtoupper(self::COOKIE_KEY);
        $headerVal = is_array($headers) ? ($headers["X_{$keyUp}"] ?? null) : null;
        $headerVal = $headerVal ?? $_SERVER["HTTP_X_{$keyUp}"] ?? null;
        if (($headerVal === '') || !is_string($headerVal)) {
            return false;
        }

        if ($headerVal === $secret) {
            return $this->saveCookie($secret);
        }

        return false;
    }

    /**
     * @param mixed $host
     * @return string
     */
    private function cookieDomain(mixed $host): string
    {
        if (!$host || !is_string($host)) {
            return '';
        }

        return '.' . implode('.', array_slice(explode('.', $host), -2));
    }

    /**
     * @param string|null $secret
     * @return bool
     */
    private function saveCookie(?string $secret): bool
    {
        $serverName = $this->cookieDomain($_SERVER['SERVER_NAME'] ?? '');
        $host = $this->cookieDomain($_SERVER['HTTP_HOST'] ?? '');
        $cookieDomainConfig = $this->cookieDomain(defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
        $domains = array_unique(array_filter([$serverName, $host, $cookieDomainConfig]));
        if (!$domains) {
            return false;
        }

        $secure = filter_var($_SERVER['HTTPS'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        $value = ($secret === null) ? '' : base64_encode(password_hash($secret, \PASSWORD_DEFAULT));
        $expires = ($secret === null) ? (time() - 86400) : 0;

        foreach ($domains as $domain) {
            setcookie(
                self::COOKIE_KEY,
                $value,
                [
                    'expires' => $expires,
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }

        return $secret !== null;
    }
}