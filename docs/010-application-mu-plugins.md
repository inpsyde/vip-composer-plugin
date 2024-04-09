---
title: Application MU Plugins
nav_order: 10
---

# Application MU Plugins



## Dealing with Composer Autoload

This plugins handles two tasks connected to Composer autoload:

- Generate a custom autoload by reusing Composer code, but ensuring no dev-dependency is included, and replacing reference to specific folders with constants like `WPCOM_VIP_CLIENT_MU_PLUGIN_DIR`, `WP_CONTENT_DIR` and `ABSPATH` to ensure maximum compatibility on VIP systems.
- Require the generated autoload file from a dynamically-generated MU plugin (called `__loader.php`) which loads the standard Composer autoload in custom local development environments, while uses the autoloader customized for VIP on online environments or for the VIP Local Development Environment.



## Activate Plugins Through Code

VIP suggests to [activate plugins through code using `wpcom_vip_load_plugin`](https://docs.wpvip.com/plugins/activate-plugins-through-code/) function.

The same dynamically-generated MU plugin used to deal with Composer autoload (`__loader.php`) also contains the code that uses `wpcom_vip_load_plugin()` to load plugins via code as suggested by VIP.

The MU plugin can be configured in project's `composer.json`.

**Please note**: in the lack of any configuration the generated MU plugin will activate via code *all* the WordPress plugins required via Composer (they must have a Composer `type` of `"wordpress-plugin"`).



### Allow-list Plugins

To control which plugins to include it is possible to list their Composer names, eventually with `*` as wildcard, for example:

```
{
    "extra": {
        "vip-composer": {
            "plugins-autoload": {
                "include": [
                    "foo/some-package",
                    "bar/*",
                    "baz/something-*"
                ]
            }
        }
    }
}
```



### Deny-list Plugins

In the case it is easier to *exclude* packages from being loaded, it is possible to use the `exclude` key:

```
{
    "extra": {
        "vip-composer": {
            "plugins-autoload": {
                "exclude": [
                    "foo/some-package",
                    "bar/*"
                ]
            }
        }
    }
}
```



### Notes

- Packages listed by they exact qualified name (combination of vendor + name) will always take precedence on glob patterns.
- If no qualified name matches, patterns are evaluated in the same order they are written, so might try to put "more generic" matches at the end
- In case both `include` and `exclude` keys are provided, `exclude` is ignored: any package matching `include` will be loaded, anything else will not.



## Loading of Env-Specific Configuration Files

This plugin supports env-specific configuration files which are loaded via a MU plugin called `__config-files-loader.php`.

See the ["Website Configuration" chapter](./008-website-configuration.md) for more details.



## Deployment Information

During the execution of `composer vip` this plugin generates an unique "Deployment ID" which is saved to a file in the `/private` folder. Learn more about it in the "*Deployment ID*" section of the ["Deployment" chapter](005-deployment.md).

This plugin delivers a MU plugin (`deploy-info.php`) which reads the content and the metadata of that file and use them to print information about the last deployment in WordPress admin footer.