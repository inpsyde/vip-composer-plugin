<?php

declare(strict_types=1);

namespace Inpsyde\Vip;

class AutomatedTestChecker
{
    public const COOKIE_KEY = 'inpsyde_autotest_key';

    /**
     * Set the `WP_RUN_CORE_TESTS` constant when on "automated tests" request, to bypass 2FA.
     * Do nothing if the constant is already set, or in production.
     *
     * @return void
     */
    public function maybeSkip2fa(): void
    {
        if (
            defined('WP_RUN_CORE_TESTS')
            || (defined('WP_ENVIRONMENT_TYPE') && (\WP_ENVIRONMENT_TYPE === 'production'))
            || (defined('VIP_GO_APP_ENVIRONMENT') && (\VIP_GO_APP_ENVIRONMENT === 'production'))
            || (defined('VIP_GO_ENV') && (\VIP_GO_ENV === 'production'))
        ) {
            return;
        }

        $this->isTargetRequest() and define('WP_RUN_CORE_TESTS', true);
    }

    /**
     * Returns true when this is and "auto test" request.
     *
     * If we have a secret configured, then we check if the secret is in cookies, headers or global
     * request. In the latter two cases, the secret is stored in
     * cookies for subsequent requests.
     *
     * @return bool
     */
    private function isTargetRequest(): bool
    {
        $secret = $this->secret();
        if ($secret === null) {
            return false;
        }

        return $this->isTargetRequestByCookie($secret)
            || $this->isTargetRequestByGlobals($secret)
            || $this->isTargetRequestByHeader($secret);
    }

    /**
     * Returns the secret stored in a constant or env var, only if it is a non-falsy string.
     *
     * @return non-falsy-string|null
     */
    private function secret(): ?string
    {
        $secret = defined('INPSYDE_AUTOTEST_KEY')
            ? \INPSYDE_AUTOTEST_KEY
            : getenv('INPSYDE_AUTOTEST_KEY');

        /** @psalm-suppress UndefinedClass */
        if (
            ($secret === false)
            && method_exists(\Automattic\VIP\Environment::class, 'get_var')
        ) {
            $secret = \Automattic\VIP\Environment::get_var('INPSYDE_AUTOTEST_KEY');
        }

        if ((((bool) $secret) === false) || !is_string($secret)) {
            return null;
        }
        /** @var non-falsy-string $secret */
        return $secret;
    }

    /**
     * Returns true if the cookie is there, and it contains the hashed secret.
     * If cookie is there but don't pass validation, the cookie is deleted.
     *
     * @param non-empty-string $secret
     * @return bool
     */
    private function isTargetRequestByCookie(string $secret): bool
    {
        $cookieVal = $_COOKIE[self::COOKIE_KEY] ?? null;
        if (($cookieVal === null) || ($cookieVal === '')) {
            return false;
        }

        if (password_verify($secret, base64_decode($cookieVal))) {
            return true;
        }

        return $this->saveCookie(null);
    }

    /**
     * Returns true if the secret is present in the request globals, and save the cookie if so.
     *
     * @param non-empty-string $secret
     * @return bool
     */
    private function isTargetRequestByGlobals(string $secret): bool
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
     * Returns true if the secret is present in the HTTP headers, and save the cookie if so.
     *
     * @param non-empty-string $secret
     * @return bool
     */
    private function isTargetRequestByHeader(string $secret): bool
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
     * Return "main" domain for given domain prefixed with a dot (which means "all subdomains").
     * E.g. `example.com` -> `.example.com` and `some.deep.subdomain.example.com` -> `.example.com`
     *
     * @param mixed $host
     * @return string
     */
    private function formatDomain(mixed $host): string
    {
        if (!is_string($host) || (((bool) $host) === false)) {
            return '';
        }

        $parts = [];
        $token = strtok($host, '.');
        while ($token !== false) {
            $parts[] = $token;
            $token = strtok('.');
        }

        if ($parts === []) {
            return '';
        }

        return '.' . implode('.', array_slice($parts, -2));
    }

    /**
     * @return list<non-empty-string>
     */
    private function allCookieDomains(): array
    {
        $byServer = $this->formatDomain($_SERVER['SERVER_NAME'] ?? '');
        $byHttp = $this->formatDomain($_SERVER['HTTP_HOST'] ?? '');
        $byConst = $this->formatDomain(defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');

        $domains = ($byServer !== '') ? [$byServer] : [];

        if (($byHttp !== '') && ($byHttp !== $byServer)) {
            $domains[] = $byHttp;
        }

        if (($byConst !== '') && ($byConst !== $byServer) && ($byConst !== $byHttp)) {
            $domains[] = $byConst;
        }

        return $domains;
    }

    /**
     * Save (or delete) the cookie. The cookie value on save the hashed secret.
     *
     * @param string|null $secret Will delete the cookie when null
     * @return bool
     */
    private function saveCookie(?string $secret): bool
    {
        $domains = $this->allCookieDomains();

        if ($domains === []) {
            return false;
        }

        $secure = filter_var($_SERVER['HTTPS'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        foreach ($domains as $domain) {
            setcookie(
                self::COOKIE_KEY,
                ($secret === null) ? '' : base64_encode(password_hash($secret, \PASSWORD_DEFAULT)),
                [
                    'expires' => ($secret === null) ? (time() - 86400) : 0,
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
