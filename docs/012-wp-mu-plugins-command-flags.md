---
title: WP and MU Plugins Command Flags
nav_order: 12
---

# WP and MU Plugins Command Flags

When [building a custom local environment](.004-custom-local-dev-env.md) via `composer vip --local`, the command, among other things, needs to:

- Download WordPress core
- Download [VIP Platform MU plugins](https://docs.wpvip.com/vip-go-mu-plugins/)

Both this operations can be customized via command flags and configurations.



## WordPress

### Configuring the Version

First of all, to download WordPress the plugin needs to know which version. In the lack of configuration the latest version is used. To customize the version, it is possible to use the `extra.vip-composer` section of `composer.json`:

```json
{
	"extra": {
        "vip-composer": {
            "wordpress": {
                "version": "6.*"
            },
        }
    }
}
```

The `version` value can be anything supported by Composer, plus the string `"latest"` which is the default in case of no configuration.



### WordPress Download Logic

The `composer vip --local` command downloads WordPress from official release zip files ("no content" version) in 3 cases:

- The first time the command is run (or in any case the WordPress folder is not found).
- `"latest"` is used as version requirement in plugin configuration in `composer.json` and a new (stable) WP release happened.
- The currently installed version does not satisfy the requirements in requirement in `composer.json`.

This means that even if a newer version of WordPress is released that would satisfy the requirements, but the currently installed version *also* satisfies the requirements **no** installation is made.

For example if the `composer.json` configuration says `6.4.*`, the currently installed version is `6.4.1` and the `6.4.2` is available, WordPress is *not* updated by default.

Unlike the [VIP Local Development Environment](./003-vip-local-dev-env.md), this plugins can *not* synchronize the WordPress version configured in `composer.json` with the version used on the VIP environment, so this must be done manually.

 

### Force WP Update

To force WP update, the `--update-wp` flag can be used in combination with `composer vip --local`.

Essentially,  `composer vip --local --update-wp` means: *"update local environment and also force the update of WP if a new acceptable version is available"*.

Please note that this is *not* relevant on deployment. When deploying, there's no reason to download WordPress core. The online VIP environments will use whatever [WP version is configured in the VIP dashboard](https://docs.wpvip.com/infrastructure/environments/software-management/#h-wordpress).



### Skip WP Update

The `--skip-wp` flag tells the command to **never download WP**. This might be useful when, for example, version requirement is set to `"latest"` but one wants to save the time necessary to download and unzip WordPress (which might take a while, especially on slow connections).



## VIP Platform MU Plugins

The most time-consuming task the first time `composer vip --local` is executed is to download VIP Platform MU plugins. Those plugins accounts for over 300 Mb in total.

This is why the `composer vip --local`, by default, only downloads the MU plugins if they are not there. So presumably the first time ever the command is ran, or if the folder is deleted by hand. To be clear: there's *no* version check, if the folder is found, no download is done. 



### Force MU Plugins Update

To force MU plugins update, the `--update-vip-mu-plugins` flag can be used in combination with `composer vip --local`.

Essentially,  `composer vip --local --update-vip-mu-plugins` means *"update local environment and also force the update of VIP Go MU plugins"*



### Skip MU Plugins Update

The `--skip-vip-mu-plugins` flag tells the command to **never download VIP Platform MU plugins**. This is useful when MU plugins are not there (never downloaded or deleted by hand), but one wants to save the time necessary to download them, for any reason.

Must be noted that if MU plugins are not there and `--skip-vip-mu-plugins` is used, the local installation will not be functional.