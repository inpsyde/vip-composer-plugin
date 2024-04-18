---
title: Plugin Configuration
nav_order: 7
---

# Plugin Configuration

This Composer plugin can work *without any configuration*, however, it provides a pretty wide set of configurations that can be used to customize the default behavior.

**Configuration resides in the "website project" `composer.json` in the `extra.vip-composer` section.**



## Configuration Cheat-Sheet

Here's the full list of configuration values alongside their default. As a reminder, all configurations are optional.

```json
{
    "extra": {
        "vip-composer": {
            "git": {
                "url": "",
                "branch": ""
            },
            "plugins-autoload": {
                "include": [],
                "exclude": []
            },
            "custom-env-names": [],
            "wordpress": {
                "version": "latest",
                "local-dir": "public",
                "uploads-local-dir": "uploads"
            },
            "vip": {
                "local-dir": "vip",
                "muplugins-local-dir": "vip-go-mu-plugins"
            },
            "dev-paths": {
                "muplugins-dir": "mu-plugins",
                "plugins-dir": "plugins",
                "themes-dir": "themes",
                "languages-dir": "languages",
                "images-dir": "images",
                "vip-config-dir": "vip-config",
                "config-dir": "config",
                "private-dir": "private"
            }
        }
    }
}
```



## In Detail

### `vip-composer.git`

This object controls the Git configuration for VIP GitHub repository. See the ["Deployment" chapter](./005-deployment.md) for more info.

Note: the **URL must be provided in the HTTPs format** (because easier to validate), even if the command (if possible) will use SSH to interact with GitHub.

While optional, configuring at least the `url` allows less verbose deployment commands.



### `vip-composer.plugins-autoload`

This object controls the generation of a MU plugin that acts as a loader for plugins, activating them through code. See the "*Activate Plugins Through Code*" section in the ["Application MU Plugins" chapter](./010-application-mu-plugins.md) for more info.

Optional, but please keep in mind that not having any configuration *all* WordPress plugins installed via Composer will always be activated via code (and so can't be deactivated via WP admin).



### `vip-composer.custom-env-names`

This is the custom list of environment names used by the VIP application. Only necessary when using uncommon environment names. See the "*'Env' Files in Separate Packages*" section of the ["Website Configuration" chapter](./008-website-configuration.md) for more info.



### `vip-composer.wordpress`

Only useful for [custom local development environment](./004-custom-local-dev-env.md). Allow to customize the WP version and the names of the folder (under root) where to save WP files and uploads. See also the ["WP and MU Plugins Command Flags" chapter](012-wp-mu-plugins-command-flags.md) for more info regarding the version.



### `vip-composer.vip`

Allow customization of the names of folder (under root) where to place VIP files (this is the VIP-skeleton-like folder) and VIP Platform MU plugins. Changing these values should be extremely rarely needed.



### `vip-composer.dev-paths`

Allow customization of the names of folder (under root) where to place ["dev-paths"](./011-managing-dev-paths.md). Changing these values should be extremely rarely needed.