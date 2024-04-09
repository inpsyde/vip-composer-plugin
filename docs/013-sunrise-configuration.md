---
title: Sunrise Configuration
nav_order: 13
---

# Sunrise Configuration

In multisite installations, a `sunrise.php` file is sometimes needed to bootstrap the website. VIP ships a custom `sunrise.php`, so clients are required to put the code they would normally place in that file into a file named `client-sunrise.php`.

See [VIP `sunrise.php` documentation](https://docs.wpvip.com/wordpress-on-vip/multisites/sunrise-php/).

This plugins ships **a pre-defined `client-sunrise.php`** which handles early redirects and multiple domain mapping to a single site. The most common use cases are:

- Multi-language sites wanting to redirect a "root" domain such as `example.com` to the home page with the default language, for example `example.com/en`.

- To redirect from "*www*" to "*non-www*" variant of the same domain, or the other way around.

- To redirect requests to a retired domain to the new one.

- To map multiple domains to the same site, while keeping the domain in the address bar (no redirect)



## Configuration files

The `client-sunrise.php` included in this plugin expects a configuration file in the `vip-config/` folder. It can be either:

- a `sunrise-configuration.json` JSON file
- a `sunrise-configuration.php` PHP file.

The data "schema" is the same, as the PHP file is expected to return an associative array. The JSON file might be easier to read, or parse programmatically, the PHP file has more flexibility.

Either way, the configuration consists in a map of "source" host names to some "target" options.



## Data format

### Redirect Configuration Basics

In its simplest form, the expected configuration data looks like this:

```json
{
    "source-domain.example": "target-domain.example"
}
```

or, in PHP:

```php
return ["source-domain.example" => "target-domain.example"];
```

Which means: redirect any request to `source-domain.example` to `target-domain.example` forwarding path and query string. For example, a request to:

```
https://source-domain.example/some/path?foo=bar
```

would be forwarded to:

```
https://target-domain.example/some/path?foo=bar
```

To be noted that while the "source" is always expected to be just a domain, the "target" could contain a path or even be a full absolute URL.

For example:

````json
{
    "example.com": "example.com/en"
}
````



### Additional Target Options

It might be desirable to do *not* forward the path or the query string. That is possible by using an object as _target_ configuration instead of a string. Something like the following:

```json
{
    "example.com": {
        "target": "example.com/en",
        "preservePath": false,
        "preserveQuery": false,
    }
}
```

When both `preservePath` and `preserveQuery` are `true`, the object configuration has the exact same meaning of using a target string.

The object configuration, offers two more properties:

- `status`, to change HTTP status code (default is `301`)
- `additionalQueryArgs`, that appends query variables to the URL
- `filterCallback`, that permits to customize the target URL using a callback

An example:

```php
return [
    "example.com" => [
        "target" => "example.com/en",
        "preservePath" => false,
        "preserveQuery" => true,
        "additionalQueryVars" => [
            'utm_campaign' => 'internal-redirect',
            'utm_source' => Inpsyde\Vip\currentUrlHost(),
            'utm_medium' => 'Referral',
        ],
        "filterCallback" => function (string $url): string {
            if (isset($_GET['uid'])) {
                $url = explode('?', $url)[0];
            }
            return $url;
        },
    ],
];
```

The PHP code must be mostly pure-PHP, as WP is not fully loaded yet, but [this plugin's helpers](./009-application-helpers.md) can be safely used. As shown above.

It is worth noting that `additionalQueryVars` query vars can also be used to _remove_ query variables, by setting the value to `null`.

Take the following example:

```json
{
    "example.com": {
        "target": "example.com/en",
        "preservePath": false,
        "preserveQuery": true,
        "additionalQueryVars" => [
        	"uid" => null
        ]
    }
}
```

With the configuration above, a request to:

```
https://example.com/some/path?foo=bar&uid=123
```

would be redirected to:

```
https://example.com/en?foo=bar
```



### Default Configuration

Starting from the last snippet, let's imagine that the `additionalQueryVars` and `filterCallback` is something we want to apply to _all_ the domain we set up a redirect for. It would be  pretty verbose to repeat those 11 lines of code for multiple domains. 

In such cases, it is possible to configure a set of "default" configuration to apply to every domain configuration. For example:

```php
return [
    ":default:" => [
        "additionalQueryVars" => [
            'utm_campaign' => 'internal-redirect',
            'utm_source' => Inpsyde\Vip\currentUrlHost(),
            'utm_medium' => 'Referral',
        ],
        "filterCallback" => function (string $url): string {
            if (isset($_GET['uid'])) {
                $url = explode('?', $url)[0];
            }
            return $url;
        },
    ],
    "example.com" => "www.example.com",
    "example.it" => "www.example.it",
    "example.es" => "www.example.es",
    "example.de" => "www.example.de",
];
```

Defaults are _merged_ with individual items configuration, that can partially or totally replace the default values.

All the configuration options, excluding "target" can be set in defaults.



### Environment-Specific Configuration

In the same configuration file, it is possible to configure values to target only specific environments. For example:

```json
{
    "env:production": {
        "example.com": "www.example.com"
    }
}
```

Defaults can be set per environment as well:

```php
return [
    ":default:" => [
        "additionalQueryVars" => [
            'utm_campaign' => 'internal-redirect',
            'utm_source' => Inpsyde\Vip\currentUrlHost(),
            'utm_medium' => 'Referral',
        ],
    ],
    "env:production" => [
        ":default:" => [
            "filterCallback" => function (string $url): string {
                if (isset($_GET['uid'])) {
                    $url = explode('?', $url)[0];
                }
                return $url;
            },
        ],
    ],
    "example.com" => "www.example.com",
    "example.it" => "www.example.it",
    "example.es" => "www.example.es",
    "example.de" => "www.example.de",
];
```

When defaults are set both at the root level and at environment level, like in the example above, the environment-specific configuration takes precedence in that environment. Please note there's no "merging" between the two defined defaults. Merging only happens between the individual domain settings and the either defaults that "won".



### Dynamic Configuration

Sometimes it is required to do dynamically calculate the target URL or configuration. The `filterCallback`, being a callback, can help with that.

However, it is important to note that `filterCallback` will only be used after a target URL is already formed, merging individual source host configuration with defaults. But if the target itself needs to be dynamic we can't use that.

Because of that, we can use callbacks also for the two properties:

- `target`
- `additionalQueryVars`

It means that instead of being a string the first, and an array the second, both can be a callback that returns a string (for `target`) or an array (for `additionalQueryVars`).

Such a callback would be called only if the current domain matches the source domain, and it will receive as parameters the source domain that matched, and the array of configuration for that source domain.

One use case for this is to perform a redirect based on the source *path*. In fact, the source key is expected to always be a domain, but we might want to redirect only in the case of certain paths. Take the following example:

```php
{
    'example.com': {
        'target': static function (): ?string {
            $currentPath = Inpsyde\Vip\currentUrlPath();
            return str_starts_with($currentPath, '/products/')
                ? 'products.example.com' . substr($currentPath, 9)
                : null;
        }, 
        'preservePath': false
    }
}
```

By returning `null` as target when current path does not start with `/products/`, we prevent any redirect to happen in that case.

When the path starts with `/products/` we redirect to a sub-domain and a partial path forwarding.

So while a request to `https://example.com/foo` would *not* be redirected, a request to `https://example.com/products/foo` would be redirected to 

`https://products.example.com/foo`.



#### Entirely Dynamic Configuration

The entire configuration for one source host can be a callback. Take the following example:

```php
return [
    'example.dev' => static function (): string|array {
        if (($_GET['utm_campaign'] ?? '') === 'product') {
            return [
                'target' => "//www.example.dev/product-campaign",
                'preservePath' => false,
                'additionalQueryVars' => [
                    'utm_campaign' => null,
                    'utm_source' => null,
                    'utm_medium' => null,
                    'utm_content' => null,
                ],
            ];
        }
        return "www.example.dev";
    },
];
```

We can't achieve the same result by using a callback for `target` and for `additionalQueryVars` because we need also to set `preservePath` dynamically. 

We could use a static `"www.example.dev"` as `target` value and then use `filterCallback` to fully calculate the final URL based on `$_GET`, but we would duplicate a lot of logic that the plugin can do for us. Using the approach above is not the _only_ way of doing this, but it is the more convenient.

Please note that unlike callbacks for `target` or `additionalQueryVars`, it is not possible to pass as parameters to this callback the current host nor the array of configuration (considering the callback has to return the array of configuration), so a callback for the entire configuration will receive no parameters.



### Dynamic Defaults and Env-Specific Configuration

Defaults configuration (key `:default:`) and env-specific configuration (key `env:<env name>`) can also be entirely dynamic, so be a callback that return a configuration array.

However, because such configuration is needed for _all_ the source domains, it must be  called early, as soon as the configuration file is loaded. Which means it is going to be called on every single request for every site. Because of obvious performance implications, please do that only if really necessary, making sure to keep the used callback as performant as possible.



## Domain Mapping

In the sections above we have documented how we can _redirect_ from one URL to another. However, one common reason to use `sunrise.php` is not to redirect, but to make available the same site in a network to multiple domains.

For example, let's assume that we want to serve all REST endpoints from `api.example.com`, while regular web request from `example.com`. WordPress would by default prevent that, as it is not possible to have the same site respond to two different domains.

We can do that by using the same configuration used for redirects, but using the `redirect` target property set to `false`.

```json
{
    "example.com": { "target": "api.example.com", "redirect": false }
}
```

Please note that generated URLs (e. g. via `get_permalink()`, `home_url()`, `rest_url()`, `admin_url()`, etc...) would still use the "main" domain, so `example.com` in the example above.

URL can be filtered manually, or it is possible to set the `Inpsyde\Vip\SUNRISE_FILTER_ALT_DOMAIN_URLS` constant to `true`. Doing that, the plugin will filter `"set_url_scheme"` replacing the domain in pretty much all generated URLs.

This has performance implications, considering the filter is potentially called a lot of times during a request. It could still be useful when we want to transparently serve a site from multiple domains, for example, during a migration phase.

**Please note that other configuration options such us `status`, `preservePath`, `preserveQuery`, `additionalQueryVars` or `filterCallback` are ignored when `redirect` is `false`**.



### The www limitation

One limitation of the multiple domain mapping, that comes from WP core, is that is *not* possible to map both a "*www*" and a "*non-www*" variant of a same domain. For example, `www.example.com` and `example.com` can *not* be both used in a WP multisite, not to point to the same site, nor to point to two different sites. Only redirect from one to the other is possible.



## Override

Despite the sunrise configuration documented above should be flexible enough to serve the purposes `sunrise.php` is usually used for, we can't exclude there are "special cases" to be handled.

By creating a file named `client-sunrise.override.php` in the `vip-config/` folder, it will be loaded, enabling the handling of "special cases" for any custom code while keeping the configuration documented in this chapter still fully functional.

Please note such a file is required on every single request for every site. Because of obvious performance implications, please do that only if really necessary, making sure the code in it is as performant as possible.
