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
 * When redirect is `true` (default), the current path and query are, by-default, forwarded.
 * That is, if the current URL visited is `https://example.dev/foo?x=y`, with the config above, the
 * user is redirected to: `https://www.example.dev/foo?x=y`. To prevent the forwarding of path or
 * query, it is possible to use the `preservePath`/`preserveQuery` option, for example:
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
 * Of course, the same works also for JSON configuration.
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
 */

declare(strict_types=1);

namespace Inpsyde\Vip;

if (
    !function_exists(__NAMESPACE__ . '\\isWebRequest')
    || !function_exists(__NAMESPACE__ . '\\loadSunriseConfigForDomain')
    || !function_exists(__NAMESPACE__ . '\\buildFullRedirectUrlFor')
    || !function_exists(__NAMESPACE__ . '\\earlyRedirect')
) {
    return;
}

/**
 * @param \WP_Site_Query $query
 * @return void
 *
 * phpcs:disable Generic.Metrics.CyclomaticComplexity
 * phpcs:disable Inpsyde.CodeQuality.FunctionLength
 */
function parseSiteQueryOnMultisiteLoad(\WP_Site_Query $query): void
{
    if (!doing_action('parse_site_query')) {
        return;
    }

    remove_action('parse_site_query', __FUNCTION__);

    static $done;
    if ($done || did_action('ms_loaded') || !isWebRequest()) {
        return;
    }

    $done = true;
    $queryDomain = $query->query_vars['domain'] ?? null;
    $queryDomains = $query->query_vars['domain__in'] ?? null;

    if (
        (($queryDomain === '') || !is_string($queryDomain))
        && (($queryDomains === []) || !is_array($queryDomains))
    ) {
        return;
    }

    $domains = is_array($queryDomains) ? $queryDomains : [$queryDomain];
    foreach ($domains as $domain) {
        $config = loadSunriseConfigForDomain($domain);
        if ($config['target'] === '') {
            continue;
        }

        if ($config['redirect']) {
            $targetUrl = buildFullRedirectUrlFor(
                $config['target'],
                $config['preservePath'],
                $config['preserveQuery']
            );

            earlyRedirect($targetUrl);
            break;
        }

        $url = $config['target'];
        if (preg_match('~^(?:https?:)?//~i', $url) !== 1) {
            $url = "//{$url}";
        }
        $parsed = parse_url($url);
        if (
            !isset($parsed['host'])
            || ($parsed['host'] === $domain)
            || ($parsed['host'] === "www.{$domain}")
            || ("www.{$parsed['host']}" === $domain)
        ) {
            continue;
        }

        $targetPath = '/' . trim($parsed['path'] ?? '', '/');
        $queriedPath = $query->query_vars['path'] ?? null;
        $queriedPaths = (array) ($query->query_vars['path__in'] ?? []);
        is_string($queriedPath) and $queriedPaths[] = $queriedPath;
        if (($queriedPaths !== []) && !in_array($targetPath, $queriedPaths, true)) {
            continue;
        }

        unset($query->query_vars['domain__in']);
        $query->query_vars['domain'] = $parsed['host'];
        break;
    }
}

add_action('parse_site_query', __NAMESPACE__ . '\\parseSiteQueryOnMultisiteLoad');

if (file_exists(__DIR__ . '\\client-sunrise.override.php')) {
    require_once __DIR__ . '\\client-sunrise.override.php';
}
