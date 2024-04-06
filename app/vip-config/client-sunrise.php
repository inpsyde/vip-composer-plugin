<?php

/**
 * This handles early redirects and multiple domain mapping to same site. It is used:
 *
 * - For multi-language site, wanting to redirect a "root" domain such as `example.com` to the
 *   domain for one language, for example `example.com/en`.
 * - To do redirect from "www" to "non-www" variant of same domain, or the other way around.
 * - To redirect a retired domain to the new one.
 * - To map multiple domains to the same site, while keeping the domain in the address bar
 *   (no redirect)
 *
 * It expects a configuration file in the `vip-config/` folder.
 * It can be either a JSON file named `sunrise-config.json` containing data such as:
 *
 * ```json
 *  {
 *      "example.com": "example.com/es",
 *      "example.dev": "www.example.dev",
 *      "www.acme.com": "acme.com",
 *      "alternative-domain.com": {"target": "main-domain.com", "redirect": false}
 *  }
 * ```
 *
 * or a PHP file named `sunrise-config.php` returning similar data:
 *
 * ```php
 *  return [
 *      // Multi-language redirect
 *      'example.com' => 'example.com/es',
 *
 *      // "non-www" to "www" redirect, and the other way around
 *      'example.dev' => 'www.example.dev',
 *      'www.acme.com' => 'acme.com',
 *
 *      // Alternative domain: no redirect will happen, both domains points same site
 *      'alternative-domain.com' => ['target' => "main-domain.com", 'redirect' => false],
 *  ];
 * ```
 *
 * The `target` key must be the target domain or URL or a callback returning that.
 * For example:
 *
 *  ```php
 *  return [
 *      'example.com' => [
 *          'target' => function (): string {
 *              $lang = $_GET['lang'] ?? 'en';
 *              if (is_array($lang, ['es', 'it', 'de'])) {
 *                  return "example.com/{$lang}";
 *              }
 *              return "example.com/en";
 *          }
 *      ],
 *  ];
 *  ```
 *
 * When redirect is `true` (default), the current path and query are, by-default, forwarded.
 * That is, if the current URL visited is `https://example.dev/foo?x=y`, with the config above, the
 * user is redirected to: `https://www.example.dev/foo?x=y`. To prevent the forwarding of path or
 * query, it is possible to use the `preservePath`/`preserveQuery` options, for example:
 *
 * ```php
 *  return [
 *      'example.dev' => [
 *          'target' => 'www.example.dev',
 *          'redirect' => true,
 *          'preservePath' => false,
 *          'preserveQuery' => false,
 *      ],
 *  ];
 * ```
 *
 * Moreover, it possible to use a `'additionalQueryVars'` key to append URL query vars to the
 * redirect URL, and a `'filterCallback'` to deeply customize the calculated URL.
 * The `'additionalQueryVars'` can be an array of parameters to add, or a callback returning such
 * array.
 *
 * Of course, the same works also for JSON configuration.
 *
 * The entire configuration for a source domain can be a callback returning the wanted target
 *  string or array. For example:
 *
 *  ```php
 *   return [
 *       'example.com' => function (): string|array {
 *           if (($_GET['utm_campaign'] ?? '') === 'product') {
 *               return [
 *                   'target' => 'https://www.example.dev/product-campaign?fromUTM=true',
 *                   'preservePath' => false,
 *                   'preserveQuery' => false,
 *               ];
 *           }
 *           return "www.example.dev";
 *       },
 *   ];
 *  ```
 *
 * The configuration can be keyed by environment, for example (using JSON, but PHP is the same):
 *
 * ```json
 *   {
 *      "env:production": {
 *          "example.com": "example.com/es",
 *          "example.dev": "www.example.dev",
 *          "www.acme.com": "acme.com",
 *          "alternative-domain.com": {"target": "main-domain.com", "redirect": false},
 *          "www.alternative-domain.com": "alternative-domain.com",
 *      }
 *   }
 *  ```
 *
 * If no environment key is found, the configuration will be applied to all environments.
 *
 * Env-specific configuration and "generic" configuration can co-exist in the same file.
 * If the same domain is used as key in both env-specific and "generic" configuration, the latter
 * takes precedence and the "generic" configuration is discarded when in that environment.
 *
 * A default configuration can be provided using the `':default:'` key. For example:
 *
 * ```php
 *    return [
 *        ':default:' => [
 *            'additionalQueryVars' => static fn (string $sourceHost, array $config): array => [
 *                'utm_campaign' => sprintf('internal-redirect-%s', Inpsyde\Vip\determineVipEnv()),
 *                'utm_source' => $sourceHost,
 *                'utm_medium' => 'Referral',
 *                'utm_content' => sprintf('%s%s', $sourceHost, Inpsyde\Vip\currentUrlPath()),
 *            ],
 *        ],
 *        'example.com' => 'example.com/es',
 *        'example.dev' => 'www.example.dev',
 *        'www.acme.com' => 'acme.com',
 *    ];
 *   ```
 *
 * The `':default:'` configuration can also reside inside an env-specific configuration:
 *
 * ```php
 *   return [
 *       'env:production' => [
 *           ':default:' => [
 *               'additionalQueryVars' => static fn (string $sourceHost, array $config): array => [
 *                   'utm_campaign' => 'internal-redirect',
 *                   'utm_source' => $sourceHost,
 *                   'utm_medium' => 'Referral',
 *                   'utm_content' => sprintf('%s%s', $sourceHost, Inpsyde\Vip\currentUrlPath()),
 *               ],
 *           ],
 *       ],
 *       'example.dev' => 'www.example.dev',
 *       'www.acme.com' => 'acme.com',
 *   ];
 *  ```
 */

declare(strict_types=1);

namespace Inpsyde\Vip;

/**
 * @psalm-type config-item = array{
 *      "target": string|callable|null,
 *      "redirect": bool,
 *      "status": int,
 *      "preservePath": bool,
 *      "preserveQuery": bool,
 *      "additionalQueryVars": array|callable,
 *      "filterCallback": callable|null
 *  }
 */
class SunriseRedirects
{
    private static bool $done = false;

    /** @var list{string, string} */
    private static array $hosts = ['', ''];

    /**
     * By looking at currently queried domain, the current host and any configuration for it,
     * determines if we have to redirect to another domain, adjust the query arguments to query an
     * alternative domain, or do nothing.
     *
     * This method is called once, very early, filtering the site query triggered by
     * `get_site_by_path()` (via `get_sites()`) called inside `ms_load_current_site_and_network()`.
     *
     * @param \WP_Site_Query $query
     * @return void
     *
     * @see https://developer.wordpress.org/reference/functions/get_site_by_path/
     * @see https://developer.wordpress.org/reference/functions/ms_load_current_site_and_network/
     * @see https://developer.wordpress.org/reference/functions/get_sites/
     * @wp-hook parse_site_query
     */
    public static function handleQuery(\WP_Site_Query $query): void
    {
        if (
            !doing_action('parse_site_query')
            || !function_exists(__NAMESPACE__ . '\\isWebRequest')
        ) {
            return;
        }

        remove_action('parse_site_query', [static::class, 'handleQuery']);
        if (static::$done || did_action('ms_loaded') || !isWebRequest()) {
            return;
        }

        static::$done = true;
        $domains = static::queryDomains($query);
        if ($domains === []) {
            return;
        }

        $sourceHost = currentUrlHost();
        if (!in_array($sourceHost, $domains, true)) {
            return;
        }

        $config = loadSunriseConfigForDomain($sourceHost);

        $target = $config['target'];
        if (is_callable($target)) {
            $target = $target($sourceHost, $config);
        }
        if (!is_string($target) || ($target === '')) {
            return;
        }

        if ($config['redirect']) {
            static::handleRedirect($target, $sourceHost, $config);
            return;
        }

        $targetHost = static::determineTargetHost($target, $sourceHost, $query);
        if ($targetHost === null) {
            return;
        }

        static::$hosts = [$targetHost, $sourceHost];
        static::handleQueryRewrite($query);
    }

    /**
     * Filters generated URLs on the website.
     *
     * When rewriting the site query to query an alternative domain, all the URL on the site
     * would still be using the target URL, creating issues, especially for assets loading.
     * This method filter pretty much all URLs generated on the site, to target domain in the URL,
     * so that we can (almost) transparently visit the site with another domain.
     * This has some performance implications, and it is disabled by default. It can be enabled by
     * setting the `Inpsyde\Vip\SUNRISE_FILTER_ALT_DOMAIN_URLS` constant to true.
     * This is a public static function, so it is easy to remove programmatically.
     *
     * @param mixed $url
     * @return mixed
     *
     * @wp-hook set_url_scheme
     */
    public static function maybeReplaceUrl(mixed $url): mixed
    {
        [$search, $replace] = static::$hosts;
        if (($search !== '') && ($replace !== '') && is_string($url)) {
            $url = preg_replace("~^(https?://){$search}([?/#]?.*)?$~", "$1{$replace}$2", $url);
        }

        return $url;
    }

    /**
     * Do the redirect when the configuration for current domain tell us to do it.
     *
     * @param non-empty-string $target
     * @param string $sourceHost
     * @param config-item $config
     * @return void
     */
    private static function handleRedirect(string $target, string $sourceHost, array $config): void
    {
        $targetUrl = buildFullRedirectUrlFor(
            $target,
            $config['preservePath'],
            $config['preserveQuery']
        );

        if ($targetUrl === null) {
            return;
        }

        if ($config['additionalQueryVars'] !== []) {
            $targetUrl = static::mergeQueryVars(
                $targetUrl,
                $config['additionalQueryVars'],
                $sourceHost,
                $config
            );
        }

        if ($config['filterCallback'] !== null) {
            try {
                $targetUrl = $config['filterCallback']($targetUrl);
            } catch (\Throwable) {
                $targetUrl = '';
            }
            if (($targetUrl === '') || !is_string($targetUrl)) {
                return;
            }
        }

        earlyRedirect($targetUrl, $config['status']);
    }

    /**
     * Adjust site query args when the configuration for current domain tell us to not redirect.
     *
     * @param \WP_Site_Query $query
     * @return void
     */
    private static function handleQueryRewrite(\WP_Site_Query $query): void
    {
        [$targetHost] = static::$hosts;
        if ($targetHost === '') {
            return;
        }

        $query->query_vars['domain__in'] = '';
        $query->query_vars['domain'] = $targetHost;

        if (
            defined(__NAMESPACE__ . '\\SUNRISE_FILTER_ALT_DOMAIN_URLS')
            && SUNRISE_FILTER_ALT_DOMAIN_URLS
        ) {
            add_filter('set_url_scheme', [static::class, 'maybeReplaceUrl']);
        }
    }

    /**
     * @param string $url
     * @param array|callable $vars
     * @param string $sourceHost
     * @param config-item $config
     * @return string
     */
    private static function mergeQueryVars(
        string $url,
        array|callable $vars,
        string $sourceHost,
        array $config
    ): string {

        try {
            is_callable($vars) and $vars = $vars($sourceHost, $config);
            if (!is_array($vars)) {
                return $url;
            }
        } catch (\Throwable) {
            return $url;
        }

        return mergeQueryVars($url, $vars);
    }

    /**
     * Based on configuration, determine the domain to replace in site query.
     *
     * This does not work for www to non-www queries and vice-versa, as we don't want to support
     * both variants for the same site (use redirect instead).
     * It also does not work for changes in path, e.g. it is not possible to rewrite `main.com` with
     * `alternative.com/something`, but only to `alternative.com`
     *
     *
     * @param non-empty-string $target
     * @param string $sourceDomain
     * @param \WP_Site_Query $query
     * @return non-empty-string|null
     */
    private static function determineTargetHost(
        string $target,
        string $sourceDomain,
        \WP_Site_Query $query
    ): ?string {

        $url = $target;
        if (preg_match('~^(?:https?:)?//~i', $url) !== 1) {
            $url = "//{$url}";
        }
        $parsed = parse_url($url);
        if (
            !isset($parsed['host'])
            || ($parsed['host'] === '')
            || ($parsed['host'] === $sourceDomain)
            || ($parsed['host'] === "www.{$sourceDomain}")
            || ("www.{$parsed['host']}" === $sourceDomain)
        ) {
            return null;
        }

        $targetPath = '/' . trim($parsed['path'] ?? '', '/');
        $queriedPath = $query->query_vars['path'] ?? null;
        $queriedPaths = (array) ($query->query_vars['path__in'] ?? []);
        is_string($queriedPath) and $queriedPaths[] = $queriedPath;
        if (($queriedPaths !== []) && !in_array($targetPath, $queriedPaths, true)) {
            return null;
        }

        return $parsed['host'];
    }

    /**
     * Determine all domains currently queried.
     *
     * Because this is done very early, during `ms_load_current_site_and_network()`, this query only
     * happens because of `get_site_path()` called inside that function, which means there will be
     * just one domain, or both "www" and "non-www" variants of the same domain.
     * This method normalizes the input and returns an array which should have one or two items.
     *
     * @param \WP_Site_Query $query
     * @return array
     */
    private static function queryDomains(\WP_Site_Query $query): array
    {
        $domains = $query->query_vars['domain__in'] ?? [];
        is_array($domains) or $domains = [];
        $domain = $query->query_vars['domain'] ?? null;
        ($domain !== null) and $domains[] = $domain;

        return $domains;
    }
}

add_action('parse_site_query', [SunriseRedirects::class, 'handleQuery']);

if (file_exists(__DIR__ . '/client-sunrise.override.php')) {
    require_once __DIR__ . '/client-sunrise.override.php';
}
