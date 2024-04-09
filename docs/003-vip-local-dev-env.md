---
title: VIP local development environment
nav_order: 3
---

# VIP Local Development Environment

The [VIP Local Development Environment](https://docs.wpvip.com/vip-local-development-environment/) is a Lando-based environment, that mimics as much as possible the online VIP servers.

There are gotchas and limitations. Please refer to the official documentation.

Besides dealing with the containers and providing useful helper commands, from an aplication perspective it also deals with:

- Copy/symlink the various folders in the skeleton (and/or setting configuration constants) so that WordPress can find them
- [VIP platform MU plugins](https://docs.wpvip.com/vip-go-mu-plugins/)
- Early loading of [pre-configuration files](https://github.com/Automattic/vip-go-mu-plugins/blob/f80274212ac812f0cc9fc1b0045c69df3673081b/000-pre-vip-config/requires.php#L5)
- Loading of [user configuration](https://docs.wpvip.com/wordpress-skeleton/vip-config-directory/)
- [Handling of `sunrise.php`](https://docs.wpvip.com/wordpress-on-vip/multisites/sunrise-php/#h-configuration-for-local-development)



## The workflow

The VIP Local Development Environment has [an option to use a local folder to load app code ](https://docs.wpvip.com/vip-local-development-environment/create/#client-code).

We can leverage that option to implement the following workflow:

1. Prepare the `vip/` folder locally using the `composer vip` command, so that it will resemble the code we would push to VIP
2. Build the VIP local development environment using the `--app-code` flag pointing the `/vip` folder we have built.



## Preparing the `vip` folder

To prepare the environment for the VIP Local Development Environment, after having installed Composer dependencies, run:

```shell
composer vip --vip-dev-env
```

It will do, in order:

- Copy the default configuration, helpers, and MU plugins from the library folder to the website project's `/vip` folder.
- Copy environment-specific configuration files from external packages, to the website project's `/vip/vip-config/env` folder.
- Copy "development paths", that is, plugins and themes part of the website project repository itself, into the `/vip` folder.
- Generate a MU plugin that loads MU plugins installed via Composer. In fact, because Composer place MU plugins inside a folder, WordPress don't load them by default. The same MU plugins also takes care of force-activating regular plugins listed in configuration. This practice is [recommended by VIP](https://docs.wpvip.com/plugins/activate-plugins-through-code/).
- Generate a customized version of Composer autoloader specifically designed to be uploaded to VIP. Alongside a MU plugin that loads it.
- Generate a deployment "id" and "version" (if applicable), and save them in two files in website project's `/vip/private` folder.



## Create the environment

After `composer vip --vip-dev-env` finishes its work, it's time to spin up the environment. Please follow [official documentation](https://docs.wpvip.com/vip-local-development-environment/create/).

As a spoiler, you'd need to do something along the lines of:

```shell
vip dev-env create --slug="my-vip-site" --title="My VIP Site" --multisite --app-code="./vip"
```

The most important bit here is the `--app-code` parameter.

In the official documentation, please pay attention to the "[Creating a local environment based on a VIP Platform environment’s settings](https://docs.wpvip.com/vip-local-development-environment/create/#creating-a-local-environment-based-on-a-vip-platform-environment-s-settings)" 
section, as it is pretty useful to build environments as much as possible similar to the production servers.



## Version Control

To use the same website repository folder to execute the local environment but also to develop and so to keep the files under version control, it is necessary to use ignore from the VCS in use files and folders generated by the plugin.

An example for a `.gitignore` file could be:

```
/vip
/vip-config/env/local.php
```
