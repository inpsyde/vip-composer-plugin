---
title: Website Configuration
nav_order: 8
---

# Website Configuration

WordPress sites are usually configured using constants defined in the `wp-config.php` file.

On VIP sites, we don't have access to `wp-config.php`, and VIP prescribes to have a `vip-config/vip-config.php` file where to set the same constants (with some limitations, please refer to the [VIP vip-config documentation](https://docs.wpvip.com/wordpress-skeleton/vip-config-directory/)).

Moreover, sometimes in multisite installations, a `sunrise.php` file is needed to bootstrap the website. This file is also not available on VIP, so they support a `client-sunrise.php` file instead. See [VIP `sunrise.php` documentation](https://docs.wpvip.com/wordpress-on-vip/multisites/sunrise-php/).

**This plugin ships with both a `vip-config.php` and a `client-sunrise.php` file that are copied to the correct folder in the website project when running `composer vip`.**



## Included `vip-config.php`

The `vip-config.php` file coming with this plugin will:

- Load a set of helper functions. See the ["Application Helpers" chapter](./009-application-helpers.md) for more details.
- Map the VIP environment type to one of the supported [WordPress environment types](https://developer.wordpress.org/reference/functions/wp_get_environment_type/). More on this below.
- Define sensible defaults for WordPress constants related to debugging (based on current environment), security, and privacy.
- Enable a workflow that disables 2FA authentication when running automated tests. See the ["Disable 2FA for Automated Tests" chapter](014-disable-2fa-automatest-tests.md) for more details.



## Included `client-sunrise.php`

The `client-sunrise.php` file coming with this plugin allows the configuration of:

- "Early" redirects from a domain to another (including, but not limited to, *www* to *non-www* redirects and the other way around)
- Point multiple domains to the same site in the network.

More on this in the ["Sunrise configuration" chapter](/013-sunrise-configuration.md).



## VIP to WP Environment Type Mapping

WordPress only supports one of the following environment types:

- `local`
- `development`
- `staging`
- `production`

When calling `wp_get_environment_type()` one of these four values is returned, defaulting to `production` if there's no configuration in place or an invalid configuration is found.

VIP instead, allows any kind of environment name. The `vip-config.php` file coming with this plugin, by using one of the delivered helpers, maps the value of the VIP environment type (that is defend by VIP) to one of the values supported by VIP.

The mapping follow this rules:

- anything that *starts* with "*prod*", "*live*", or "*public*" are mapped to "*production*"
- anything that starts with "*dev*" is mapped to "*development*"
- anything that starts with "*local*" is mapped to "*local*"
- anything else is mapped to "staging"

In case of a custom mapping, e. g. to map a VIP environment type named "*test*" to "*development*" WP environment type it is possible to use and "override" file (more on this below) and define `WP_ENVIRONMENT_TYPE`constant.



## Configuration from Environment-Specific Files

Considering we don't have access to `wp-config.php` and we are not supposed to edit the `vip-config.php` files that comes with this plugin, we need "some place" where to enter configuration.



### Local "Env" Files

This plugins supports PHP configuration files named after the current environment type and placed in the `vip-config/env/` folder in the "website project" repository root. For example, if we have a file named `vip-config/env/preprod.php` this file will be loaded when the current VIP environment is `preprod`. If such file is not found, the plugin searches for a file named after the mapped WP environment type (in this case `staging`) and loads it if found.

Only for `local` environment type, if no `vip-config/env/local.php` is found, the plugin searches for `vip-config/env/development.php`.

In any case, if a `vip-config/env/all.php` is found that is loaded as last. 



### "Env" Files in Separate Packages

The problem with local "env" files is a lot of repetition and copy and paste to keep them in sync across branches. For example, assuming we have a site with four environments, we likely have four Git branches. And in any of those branch we need to have the four env-specific files. 

To solve this issue, this plugin supports **separate packages whose Composer `type` is `vip-composer-plugin-env-config`**. 

These packages are expected to have in their root folder environment-specific files in all similar to those that can be placed in the `vip-config/env/` folder. The `composer vip` command will then move them to `vip-config/env/`. Being external packages required via Composer, we don't need to have multiple branches: a single branches contains files named after all supported environment can be required by several "website project" branches.

It is important to note that the `composer vip` command will *not* blindly copy any PHP file found in the root of a package with `vip-composer-plugin-env-config` Composer type. By default, only following files are copied if found:

- `local.php`
- `dev.php`
- `develop.php`
- `development.php`
- `staging.php`
- `stage.php`
- `testing.php`
- `uat.php`
- `preprod.php`
- `production.php`
- `all.php`

To allow a different set of file names, make use of the `extra.vip-composer.custom-env-names` configuration, listing the desired names (*without* the trailing `.php`).



### "Env" Files Loading Time

**Files located in `vip-config/env/` are loaded via a MU plugin** delivered by this plugin. That allow us to reference in these files any of the tools and helpers coming from [VIP Platform MU plugins](https://docs.wpvip.com/vip-go-mu-plugins/), considering that custom MU plugins are loaded after the VIP MU plugins.

The downside is that it might be too late to set some constants. To solve this issue, the `vip-config.php` files that comes with this plugin supports "override" configuration files.



## Configuration Override

A file named `vip-config/vip-config.override.php` will be loaded as first thing by the `vip-config.php` files that comes with this plugin.

That allow us to define any kind of constant that is too late to define at MU plugins load time.

Of course, being loaded this early this file must be mostly pure PHP, and it can not rely on WordPress code nor on VIP code (with some very limited exceptions), but it can still rely on [this plugin's application helpers](./009-application-helpers.md).



## A Note About Secrets

VIP suggests to not put secrets (such as API keys and the like) into configuration files, and use [environment variables instead](https://docs.wpvip.com/infrastructure/environments/manage-environment-variables/).

While this is an advice we agree with, environment variables are a relatively new VIP feature and its **current status is very limited**:

- First and foremost, there's no straighforward way to automate environment variables delivery. The process of setting them is designed around manual interaction with the VIP CLI, and the there's no API to programmatically set multiple variables at once. Moreover, variables are only delivered upon successfull deployment, but we don't know a deployment is successful until it ends. So even if we would find a way to somehow automate variables delivering via VIP CLI, there's no way to ensure an alignment between the deployed _code_ and the related environment variables. 
- On local environments, secrets stored in environment variables must be instead stored as plain old constants, which means developers needs to be still aware of them, and they are still present on developers' machines. Not to mention that is very hard to ensure a sync between local and deployed variables, considering that variables' content, for their nature, is not visible.

For all these reasons, this plugins does *not* currently offer any integration with VIP environment variables. While their usage is still possible when using this Composer plugin, there's no support for them on deployment, and unfortunately, the state of the art for us is store secrets in configuration files.