---
title: The 'composer vip' Command
nav_order: 6
---

# The `composer vip` Command



This plugin provides a single command:

```
composer vip
```

However, this command alone won't work. It is necessary to provide one or more flags to describe the task to execute.



## Flags Cheat-Sheet



### Main

- `--local` - Tell the command to build a custom local environment. See the ["Custom Local Development Environment" chapter](./004-custom-local-dev-env.md) for details.
- `--vip-dev-env` - Tell the command to prepare for the VIP Local Development Environment. See the ["VIP Local Development Environment" chapter](./003-vip-local-dev-env.md) for details.
- `--deploy` - Tell the command to prepare the files and folders for production and commit any change to VIP GitHub repository. See the ["Deployment" chapter](./005-deployment.md) for details.



### Git and GitHub Options

- `--git` - Build Git mirror folder, but do not push. To be used in combination with `--local` or `--vip-dev-env`.
- `--push` - Build Git mirror folder and push to VIP. To be used in combination with `--local` or `--vip-dev-env`.

- `--git-url` - Set the Git remote URL to pull from and push to. To be used in combination with `--deploy`,  `--local` or `--vip-dev-env`. If not provided, the value will be taken from `composer.json` in the `extra.vip-composer.git.url` configuration. 
- `--branch` - Set the Git branch to pull from and push to. To be used in combination with `--deploy`,  `--local` or `--vip-dev-env`. If not provided, the value will be taken from `composer.json` in the `extra.vip-composer.git.branch` configuration. 



### Utilities

- `--sync-dev-paths` - Synchronize local dev paths. To be used as the only option. See the ["Managing Dev Paths" chapter](./011-managing-dev-paths.md) for more details. Assumed when using `--local`.
- `--prod-autoload` - Generate production autoload. To be used standalone, or in combination with `--local`. It is assumed when using `--vip-dev-env` or anyway if Git mirror folder is created, so if any of `--git`, `--push`, or `--deploy` is used.



### WordPress and MU Plugins Options

All the following flags are intended to be used in combination with `--local`. See the ["WP and MU Plugins Command Flags" chapter](./012-wp-mu-plugins-command-flags.md) for more details.

- `--update-wp` - Force the update of WordPress core.
- `--skip-wp` - Skip the update of WordPress core.
- `--update-vip-mu-plugins` - Force the update of VIP Go MU plugins. Can also be used as standalone flag.
- `--skip-vip-mu-plugins` - Skip the update of VIP Go MU plugins.