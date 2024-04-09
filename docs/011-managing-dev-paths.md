---
title: Managing Dev Paths
nav_order: 11
---

# Managing Dev Paths



### What's that

We call "dev paths" any plugin, theme, configuration file that is part of the [VIP skeleton](https://github.com/Automattic/vip-go-skeleton) and that we keep under version control in the same "website project" repository.



### How it works

All "dev paths" can be placed in the "website project" repository root, and the `composer vip` command **will copy** them inside the`/vip` folder.

The reason is quite simple. The `/vip` folder exists is to be a 1:1 mirror of the VIP GitHub repository.

Which means a folder like `/vip/plugins` will contain plugins that are installed via Composer. Same for themes and MU plugins.

Without the "copy approach", it would mean that `/vip/plugins` (or `/vip/theme` or `/vip/client-mu-plugins`) would contain, after `composer install`, a "mix" of third party dependencies required via Composer and custom packages written for the project.

Because the latter should be surely kept under version control, but not the former, it would be necessary to rely on quite complex and hard to maintain `.gitignore` configuration.

By using "dev paths" folders copied by the command in place, the "dev paths" folders in root contain only custom work to be kept under version control, and the `/vip` folder can be completely VCS-ignored and it becomes completely disposable.

However, this approach has also **two issues**.

- "dev paths" in root folder are "copied" into `/vip` folder, which means that at any change the copy has to be done again.
- when a theme or a plugin in own folder is deleted from `/themes` or `/plugins`, it will not be deleted from `/vip/themes` or `vip/plugins`.



### The `--sync-dev-paths` Flag

The `composer vip` command provides the `--sync-dev-paths` flag to solve the first issue.

This flag must be used as the **only** flag, and makes the command sync the dev paths from the root to `/vip`.

Example:

```
composer vip --sync-dev-paths
```

Considering this is a quite fast operation it makes sense to use (if possible) any IDE feature that automatically run the above command when anything changes inside "dev paths". A filesystem "watch" functionality of some kind could also be used for the scope.



### Themes and Plugins as Part of the Website Repository

When a theme or a plugin in own folder is deleted from a "dev path" it will **not** be deleted from `/vip`.

For example, if there's a theme in `/themes/my-theme`, its files will be correctly kept in sync with `/vip/themes/my-theme` as long as the `/themes/my-theme` exists. However, if `/themes/my-theme` is deleted, `/vip/themes/my-theme` will **not** be deleted.

This issue does not affect single-file plugins. For example, a plugin located at `/plugins/my-plugin.php` will be correctly kept in sync with `/vip/plugins/my-plugin.php` and if `/plugins/my-plugin.php` is deleted, `/vip/plugins/my-plugin.php` will be deleted as well.

For this reason **it is suggested to use "dev paths" only for MU plugins and simple single-file plugins, but use a separate repository for themes** (that always comes in own folder) or more complex plugins.