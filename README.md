# VIP Go Composer Plugin



This package is a Composer plugin to be used in projects to be deployed on [VIP GO platform](https://vip.wordpress.com/documentation/vip-go/) and provides a CLI command with **two different purposes**:

1. ease the setup of a **local environment** based on Composer that is compatible with the VIP Go platform
2. ease the **automatic deploy** of the project on VIP Go



## Quick reference

The package provides a command **`composer vip`** that can be used to both **prepare a local environment**  and **deploy to VIP Go repository**.

Examples:

```shell
composer vip --local                       # prepare local environment

composer vip --deploy --branch="develop"   # deploy to VIP Go repository
```

Deploy command as shown above require some configuration in `composer.json` at very least the GitHub URL for the repository, that if not present in  `composer.json`  can be passed to the command via the `--git-url` option.

It is important to note that `composer vip` command **must be run *after* composer install|update**.

Here's a one liner to both update Composer dependencies and prepare local environment:

```shell
composer update && composer vip
```

If this is the first time you come here, it is suggested to read below for better understand of what, how and why  this command does what it does.

Skip to [Command reference](#command-reference) section for detailed documentation on the command and its available options.



## Why

VIP Go platform is a managed WordPress hosting that allows to deploy to its server via a Git commit to a repository hosted on GitHub. Different branches means different environments, and master is for production.

The repository is not the full WordPress folder, but *a sort of* the `/wp-content` folder.

*"Sort of"* because there are some differences:

- MU plugins are not saved in `mu-plugins` folder as normally they are, but in a `/client-mu-plugins` folder, because  `mu-plugins` is reserved for proprietary MU plugins always present on the platform
- There's a `/vip-config` folder that must contain at least a `vip-config.php` file that is loaded from platform `wp-config.php` and it is the place where to set constants normally located on  `wp-config.php` (not having access to the whole installation, that file is, in fact, not editable).
- There's a `/private` folder that contains not browser-accessible files, but useful to store PHP-accessible data, configuration and alike.
- There's a `/images` browser-accessible folder that contains images that can be made available for the website.
- There's no `/uploads` folder. All the media are stored on a CDN and will not be present on the server (container) filesystem at all.

More info can be found here: https://vip.wordpress.com/documentation/vip-go/understanding-your-vip-go-codebase/

On top of that, as mentioned above, VIP Go as quite a few [proprietary MU plugins](https://github.com/Automattic/vip-go-mu-plugins) that will always be loaded.

This means that to have a local environment that can be used to develop websites there's the need of different tasks:

- install WordPress
- be sure to load the `vip-config/vip-config.php` from `wp-config.php`
- install all VIP Go MU plugins

More info can be found here: https://vip.wordpress.com/documentation/vip-go/local-vip-go-development-environment/

To have  **a local environment entirely based on Composer, **where not only plugins / themes / libraries, but also WordPress itself are all installed via Composer, requires additional tasks on top of that:

- the Composer autoloader must be loaded at some early point of the request bootstrap
- the Composer autoloader that is deployed must **not** contain development requirements
- there's the need of setting custom installation paths for WordPress packages (themes / plugins / mu-plugins) because the standard path provided by [Composer installers](https://github.com/composer/installers) is not the correct one (`/wp-content/...`)
- if some MU plugin is required by Composer there must be in place some "loader" mechanism, because WordPress will not load MU plugins from sub-folders and Composer will install MU plugins in sub-folders.

To do all of this in a programmatic way, both for local development and for deploy (e.g. via some CI service), is an additional concern on top of it.

**The aim of the package is to make these tasks as simple and straightforward as possible.**



## Prerequisites

For **local** development it is necessary:

- _something_ capable of running PHP 7.1+ and MySql. Being it XAMPP, Mamp, Vagrant, Docker or anything else is not really relevant.
- a DB ready for the website
- an (updated) Git client available on the machine and accessible via the `git` command
- Composer



## Prepare the local installation

A folder on local environment must be dedicated to the project. The *example* structure is something like this:

```
\-
  |- config/
  	|- vip-config.php
  |- images/
  	|- some-image.png
  |- mu-plugins/
  	|- some-mu-plugin.php
  	|- another-plugin.php
  |- plugins/
  	|- one-plugin/
  		|- plugin.php
  	|- another-plugin/
  		|- plugin.php
  |- private/
  	|- some-file.json
  |- themes/
  	|- my-theme/
  		|- index.php
  		|- style.css
  |- composer.json
```

On the structure above only **`composer.json`** and **`config/vip-config.php`** are mandatory, all the other things are optional (and `config/` folder could be renamed to something else, if desired).

Such a structure could be used for a *monolith* repository that contains all the *things* (themes, plugins, MU plugins) necessary for the website, but chances are that some (or all) plugins / themes / mu-plugins will be located in separate repositories and required via Composer. But even in that case there could be some simple mu-plugin / plugin that is located on the same project repository because not worth a separate repository.

A real-world typical project folder (which very likely will be kept under version control) would look like this:

```
\-
  |- config/
  	|- vip-config.php
  |- images/
  	|- some-image.png
  |- mu-plugins/
  	|- some-mu-plugin.php
  	|- another-mu-plugin.php
  |- private/
  	|- some-file.json
  |- composer.json
  |- .gitignore
```

With this structure ready it is time to configure Composer.



## Composer configuration

The `composer.json` is pretty standard. There are **a few things** that must be taken into consideration:

- this plugin must be required, its name is `inpsyde/vip-composer-plugin`
- an object `extra.vip-composer` can be used to configure / customize plugin behavior. This is entirely **optional**.
- `config.vendor-dir` must be used to point the "client MU plugins folder", by default `vip/client-mu-plugins/vendor` (but outer folder name, `/vip`, might change based on configuration of `extra.vip-composer.vip.local-dir` )

The whole set of settings available, with their defaults, looks like this:

```json
{
    "config": {
        "vendor-dir": "vip/client-mu-plugins/vendor",
        "platform": {
            "php": "7.2"
        }
    },
    "extra": {
        "vip-composer": {
            "vip": {
                "local-dir": "vip",
                "muplugins-local-dir": "vip-go-mu-plugins"
            },
            "git": {
                "url": "",
                "branch": ""
            },
            "wordpress": {
                "version": "4.9.*",
                "local-dir": "public",
                "uploads-local-dir": "uploads"
            },
            "plugins-autoload": {
                "include": ["*/*"]
            },
            "dev-paths": {
                "muplugins-dir": "mu-plugins",
                "plugins-dir": "plugins",
                "themes-dir": "themes",
                "languages-dir": "languages",
                "images-dir": "images",
                "config-dir": "config",
                "private-dir": "private"
            }
        }
    }
}
```

The `config` object is something provided by Composer, and not specific of this plugin. It is shown above for completeness. The `config.platform` is set above to `7.2` because that's the version currently used on VIP Go platform, and setting this will help in getting same dependencies that will be deployed even running a different PHP version locally.

**If the `vip-composer` configuration shown above is fine for you, there's no need to add any configuration at all**, because these defaults will be used in absence of configuration.

**Note:** The first time `composer update` (or `install`) is ran for the project locally, the local environment will **not** be ready if `composer vip` is not ran as well. In fact, when using this plugin WordPress is not installed by Composer (even if a WordPress package is required in `composer.json`).



### Configuration in detail

#### `vip-composer.vip`

One of the things that the command provided by this plugin does is to **create a folder that mirrors the structure of VIP Go repository**. This folder will be located in the root of the project folder.

`vip-composer.vip.local-dir` config controls the name of that folder.

Another thing that the plugin command does is to **download VIP Go MU plugins**, and make them usable in local WordPress installation.

`vip-composer.vip.muplugins-local-dir` config controls the name of folder where those MU plugins will be downloaded (inside project root).

#### `vip-composer.git`

This object controls the Git configuration for VIP Go GitHub repository. As shown above, default settings are empty, this requires that both URL and branch must be passed as options to `composer vip` command.

Might be a good idea to at least fill in the URL to avoid having to type long commands.

Note: the **URL must be provided in the HTTPS format** (because easier to validate), even if the command (if possible) will use SSH to interact with GitHub.

#### `vip-composer.wordpress`

When run to setup local environment, the command **installs WordPress**. This configuration object controls the target location and the version that will be installed.

`vip-composer.wordpress.version` controls the installed version. If this is empty, the plugin will look into `require` object to see the version of packages with type `wordpress-core` and will use the version of the first package found with that type. This configuration accepts any of the format accepted by [Composer package links](https://getcomposer.org/doc/04-schema.md#package-links),  plus `"latest"`, to always get the latest version.

`vip-composer.wordpress.local-dir` controls the folder, inside project root, where WordPress will be installed. Note that **this folder (and not the project root) must be set as the webroot** in the local environment web server.

`vip-composer.wordpress.uploads-local-dir` controls the folder, inside project root, where the "uploads" folder will be located. The uploads folder, in facts, is located outside the WordPress folder to make the WordPress folder completely disposable without losing the media. **The folder will be symlinked** by the plugin command into `/wp-content/uploads` so WordPress will work with no issues.

#### `vip-composer.plugins-autoload`

VIP suggests to load plugins via code using [`wpcom_vip_load_plugin`](https://wpvip.com/functions/wpcom_vip_load_plugin/) function
(see [VIP documentation](https://wpvip.com/documentation/vip-go/managing-plugins/) for details).

An excerpt:

> we recommend loading your plugins from code. Loading plugins in code results in more control and a greater consistency across your production, non-production environments, and local development environments.

This package, by default, creates a loader MU plugin that uses `wpcom_vip_load_plugin` to load plugins via code as suggested by VIP.

However, in multisite installations, it might be desirable to have some plugins activated in only some of the sites, and this is not possible when loading plugins via `wpcom_vip_load_plugin`.

Thanks to `plugins-autoload` setting it is possible to select which plugins will be autoloaded via either a blacklist (list of plugins to don't autoload) or a whitelist (list of plugins to autoload).

Without any setting the default is "autoload everything".

To control which plugins to include it is possible to list them, eventually with `*` as wildcard, e.g.:

```json
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

If it is easier to _exclude_ packages from being loaded, it is possible to use the `exclude` key:

```json
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

To be noted:

- this only works with packages of type "wordpress-plugins", packages of type "wordpress-muplugins" will be always loaded
- packages listed by they exact qualified name (combination of vendor + name) will always take precedence on glob patterns.
- if no qualified name matches, patterns are evaluated in the same order they are written, so might try to put "more generic" matches at the end
- in case both `include` and `exclude` keys are provided, `exclude` is ignored: any package matching `include` will be loaded, anything else will not.


#### `vip-composer.dev-paths`

As shown above in the [Prepare the local installation](#prepare-the-local-installation) section, in the root of the project folder there might be some folder for themes,  plugins, MU plugins that can be used to include those things in the same folder / repository of the project, without using a separate repository for them. Moreover, there are folder like `config`, `private` and `images` that can be used to fill the related VIP folder.

Only the `config/vip-config.php` is mandatory all the other folders and their content is optional.

`vip-composer.dev-paths` controls the name of those folders. By default names are the same names used by VIP Go repository, only `vip-config/`  is renamed to `config/`.



## Folder structure *after* the command is ran

Assuming default configuration and an initial folder structure like this:

```
\-
  |- config/
  	|- vip-config.php
  |- images/
  	|- some-image.png
  |- mu-plugins/
  	|- some-mu-plugin.php
  	|- another-mu-plugin.php
  |- private/
  	|- some-file.json
  |- composer.json
  |- .gitignore
```

after running:

```shell
composer update && composer vip
```

(and waiting for a while) the structure of the project folder will be:

```shell
\-
  |- config/
  	|- vip-config.php
  |- images/
  	|- some-image.png
  |- mu-plugins/
  	|- some-mu-plugin.php
  	|- another-mu-plugin.php
  |- public/
  	|- wp-admin/
  		| ...            # core files
	|- wp-includes/
  		| [...]          # core files
  	|- wp-content/
  		| -> mu-plugins/ # symlink to /vip/vip-go-mu-plugins/
  		| -> plugins/    # symlink to /vip/plugins/
  		| -> themes/     # symlink to /vip/themes/
  		| -> uploads/    # symlink to /uploads
  |- private/
  	|- some-file.json
  |- uploads/
  |- vip/
  	|- vip-config/
  		|- vip-config.php
  	|- images/
  		|- some-image.png
  	|- client-mu-plugins/
  		|- vendor/
  			| [...]       # any Composer dependency
  			| vip-autoload/
  				| - autoload.php
  			|- autoload.php
  		|- __loader.php
  		|- some-mu-plugin.php
  		|- another-mu-plugin.php
  	|- themes/
  		| [...]            # any theme installed via Composer
  	|- plugins/
      	| [...]            # any plugin installed via Composer
    |- private/
        |- some-file.json
        |- deploy-id
  |- vip-go-mu-plugins/
  	| [...]                # all the VIP Go MU plugins
  |- composer.json
  |- composer.lock
  |- .gitignore
  |- wp-cli.yml
  |- wp-config.php
```

There's quite a lot going on there:

- a `/public` folder has been created to contain WordPress installation. The `wp-content/` folder is filled with symlinks to folder were "things" are actually located. This makes this folder entirely **disposable**. This needs to be set as webroot. **It should be git-ignored**.

- a `/vip` folder as been created to exactly mirror the VIP Go repository. All the files and folders inside the "dev paths" in the project root have been copied here. This make this folder entirely **disposable**  and should be **git-ignored**, whereas "dev paths" in root folder can be kept under version control.

  - `/vip/client-mu-plugins/vendor/` contain the Composer dependencies. This happen thanks to the configuration in `config.vendor-dir` shown above in the [Composer configuration](#composer-configuration) section. Besides the Composer dependencies, the folder also contains a `vip-autoload/` folder that contains the "production" autoload: no matter if Composer installed dev-dependencies, this autoloader will only handle production dependencies.
  - `/vip/client-mu-plugins/__loader.php` has been created to call `wpcom_vip_load_plugin()` function for all plugin installed (as suggested by VIP Go documentation) and, more important, to load the proper autoload depending on the environment: plugin-generated production autoload will be loaded on VIP, Composer-generated autoload will be loaded locally.
  - `/vip/private/deploy-id` file has been created. This is a simple text file, containing a single line of text (an [UUID v4](https://en.wikipedia.org/wiki/Universally_unique_identifier)).  This UUID changes at every deploy so it could be used in code to build cache keys (or "ver" query string for assets cache busting) that expire on a per-deploy base.

- an `/uploads` folder have been created (and symlinked from WordPress content folder) to store uploads. This should be **git-ignored**.

- a `/wp-cli.yml` file has been created containing a reference to WordPress path, to make it recognizable by WP CLI without having to pass the `--path` option with every command.

- a `/wp-config.php` file has been created. It is outside the WordPress folder for both convenience and to make WordPress folder completely disposable. The first time the command is run, local **database configuration needs to be entered**. The file contains already the loading of `vip/vip-config.php` (to mimic VIP Go behavior)  and the constant to make `vip-go-mu-plugins/` the source of MU plugin.


### A `.gitignore` sample

An example of `.gitignore` could be the following:

```shell
/public/
/uploads/
/vip/
/vip-go-mu-plugins/
/wp-config.php
```

but note that this is **not** generated automatically.



## Command reference

As quickly written in the beginning, this plugin provides a single command:

```shell
composer vip
```



### Options Cheat-Sheet

- `--local` - Tell the command to build the local environment
- `--deploy` - Tell the command to prepare the files and folders for production and commit any change to VIP Go GitHub repo, that is at any effect deploying the website.

- `--git-url` - Set the Git remote URL to pull from and push to. When `--local` is used, this is relevant only if `--git` or `--push` are used as well.
- `--branch` - Set the Git branch to pull from and push to. When `--local` is used, this is relevant only if `--git` or `--push` are used as well.
- `--git` - Build Git mirror folder, but do not push. To be used in combination with `--local.` Ignored if `--deploy` is used.
- `--push` - Build Git mirror and push it. To be used in combination with `--local.` Ignored if `--deploy` is used.
- `--sync-dev-paths` - Synchronize local dev paths. To be used as the only option.
- `--update-wp` - Force the update of WordPress core. To be used as the only option or in combination with `--local.` Ignored if `--deploy` is used.
- `--skip-wp` - Skip the update of WordPress core. To be used in combination with `--local.` Ignored if `--deploy` is used.
- `--update-vip-mu-plugins`  - Force the update of Vip Go MU plugins. To be used as the only option or in combination with `--local.` Ignored if `--deploy` is used.
- `--skip-vip-mu-plugins` - Skip the update of Vip Go MU plugins. To be used in combination with `--local.` Ignored if `--deploy` is used.



### In detail

#### Local

`--local` option makes the command perform a set of tasks to prepare and/or update the local environment:

1. Download WP core if necessary
2. Create and configure `wp-config.php` if necessary
3. Download VIP Go MU plugins if necessary
4. Generate a loader file to require Composer autoloader and eventual MU plugins from sub-folders
5. Symlink "content" folders into WordPress installation
6. Copy "dev paths" into VIP folder
7. Create and configure `wp-cli.yml` if necessary

This is the **default option** and it is assumed if no option is passed.



#### Deploy

`--deploy` option makes the command perform a set of tasks to prepare, sync and update the VIP Go GitHub repository:

- Clone the remote repository into a temporary folder
- Replace in this temporary folder all the updated files that has to be deployed, skipping all those that should not (e.g. dev dependencies)
- Generate a "production" version of Composer autoload
- Push the changes to remote repository

The command using this option is perfectly suitable to be run in CI pipelines.



#### Git configuration

Two commands options are available to configure Git operations:

- `--git-url` - To set the URL of Git repo to pull from / push to. If not provided, the value will be taken from `composer.json` in the `extra.vip-composer.git.url` config. If there's also no such config, the Git sync will fail.
- `--branch` - To set the branch of Git repo to pull from / push to. If not provided, the value will be taken from `composer.json` in the `extra.vip-composer.git.branch` config. If there's also no such config, the Git sync will fail.

Example:

```shell
composer vip --deploy --git-url="https://github.com/wpcomvip/my-project" --branch="develop"
```



#### Git options for local environment

By default, when `--local` option is used the plugin prepare the local environment **without** bother with Git at all. Sometimes might be desirable to look at the Git diff of what would be pushed using `--deploy`, before actually doing the deploy.

This could be achieved via the `--git` option.

When this option is used, the plugin creates a folder inside `/vip` that has an unique name prepended by a dot, something like `/.vipgit5b59852eb21c7`.

This folder will be a Git repository, build from VIP Go GitHub repo, where all changes have been committed. So by pointing a Git client / tool to this folder it is possible to "preview" changes before pushing. And, actually, by running `git push` on this folder it is possible to deploy the changes.

Sometimes it is also desirable to directly push the changes from local environment.

This could be achieved via the `--push` option. Using this option in combination with `--local` is different than just using `--deploy` by the fact that the latter will only run tasks that are relevant for _remote_ server (e.g. always skipping WP install, VIP MU plugins download...) so it is a good choice to deploy from a CI. By using `--local --push` the command will update the development environment  and **also** push changes to remote repo.

Git configuration (URL and branch) applies when using either `--git` or `--push` in the same way they do when using `--deploy`.

Example:

```shell
composer vip --local --git --branch="develop"
```



#### Managing Dev Paths

One of the things that it seems harder to grasp about the folder structure promoted by this plugins is why there are "dev paths" (plugins, themes, config and all the other folders in VIP Go structure) in the root of the project and same folders are **also copied** under `/vip` folder.

The reason is quite simple. The reason to exist of `/vip` folder is to be a 1:1 mirror of the VIP Go Git repository.

Which means that when using Composer, a folder like `/vip/plugins` will contain plugins that are installed via Composer. Same for themes and MU plugins.

If, for any reason, developers want to use the same "project" repository to include plugins, MU plugins, themes without them being on a separate repo, it would mean that  `/vip/plugins` (or `/theme` or `/client-mu-plugins`) would contain, after composer install, a "mix" of third party dependencies required via Composer and custom packages written for the project.

Because the latter should be surely kept under version control, while the former probably not, it would be necessary to rely on quite complex (and honestly hard to maintain) `.gitignore` configuration.

By using separate folders, like this plugins does, the folders in root contain only custom work to be kept under version control, and then the command takes care of copying them, alongside other dependencies that Composer takes care of placing in proper place (thanks to a custom installer shipped with the plugin).

This way the `/vip` folder can be completely Git-ignored and it also becomes completely disposable, which is a good things for folders that are automatically generated.

However this approach has also a downside. When one of those custom plugin/theme/mu-plugin in the root folder, but also anything in `/config` , `/private` and `/images`, is edited, the local installation will not recognize the changes. Because local WordPress `/wp-content` contents are symlinked to the folders inside `/vip` (git-ignored) and not to the what's in root folder (tracked).

Which means that after every change to those files the plugin command should be run again, to let it copy everything again.

Besides this being not very "developer friendly" the `--local` option makes the plugin do quite a lot of things, that are not necessary in the case, for example, a single configuration entry or a single MU plugin have been modified.

This is why the plugin command provides the option `--sync-dev-paths`.

This flag must be used as the **only** flag, and makes the command just copy the dev paths from root to `/vip`.

This is a quite fast operation. So fast that it might be used in combination with some "watch" technique (provided by IDEs or by tool of choice), that basically runs this command when anything in the "dev paths" in root folder changes. This make the developer experience much better.

Example:

```shell
composer vip --sync-dev-paths
```



#### Force or skip WP download

One of the things the command does when using `--local` is to download WordPress from official release zip file ("no content" version).

This is done in 3 cases:

- The first time the command is run (or in any case the WordPress folder is not found)
- `"latest"` is used as version requirement in plugin configuration in `composer.json` and a new (stable) release have been made
- Version requirement in `composer.json` changed (either in `require` or plugin configuration) and the currently installed version does not satisfy the new requirements.

This means that even if a newer version of WordPress is released that would satisfy the requirements, but the currently installed version _also_ satisfy the requirements **no** installation is made.

For example if the requirements says `4.9.*` and the installed version is `4.9.5` but the `4.9.7` is available, WordPress is not updated by default when `--local` option is used.

In this case an update can be forced by using `--update-wp` flag.

This flag can be used in combination with `--local` to mean _"update local environment and also force the update of WP if a new acceptable version is available"_ or it can be used **alone** to mean *"just update WP and nothing else".*

This latter usage is worthy because, as previously said, when using this plugin, `composer update` does not update WordPress.

Another option that controls the download of WordPress is `--skip-wp`.

This option tells the command to **never download WP**. Can only be used in combination with `--local`. This is useful when, for example, version requirements is set to "latest" but one wants to save the time necessary to download and unzip WordPress (which might take a while, especially on slow connections).

Must be noted that if WordPress folder is not there and `--skip-wp` is used, the local installation will not functional.

It is also worth saying that if used together `--update-wp` and `--skip-wp` will make the command fail.

Example:

```shell
composer vip --update-wp
```



#### Update or skip VIP Go MU plugins download

The most time consuming task the first time command is ran is to download VIP Go MU plugins. Those plugins accounts for around 300Mb in total, pulled from a repo with _several_ recursive submodules.

This is why the `composer vip` command by default only download the MU plugins if they are not there. So presumably the first time ever the command is ran, or if the folder is deleted by hand.

However, those MU plugins are actively developed, and it make sense from time to time update them locally to be in sync with what is on VIP Go servers.

This can be obtained via the `--update-vip-mu-plugins` flag.

This flag can be used in combination with `--local` to mean _"update local environment and also force the update of VIP Go MU plugins"_ or it can be used **alone** to mean *"just update VIP Go MU plugins".*

The latter usage is basically an alias for:

```shell
git clone --recursive git@github.com:Automattic/vip-go-mu-plugins.git
```

but:

```shell
composer vip --update-vip-mu-plugins
```

is shorter to type and easier to remember.

Another option that controls the download of Vip Go MU plugins is `--skip-vip-mu-plugins`.

This option tells the command to **never download VIP Go MU plugins**. Can only be used in combination with `--local`. This is useful when MU plugins are not there (never downloaded or deleted by hand), but one wants to save the time necessary to download them, for any reason.

Must be noted that if MU plugins are not there and `--skip-vip-mu-plugins` is used, the local installation will not be functional.

It is also worth saying that if used together `--update-vip-mu-plugins` and `--skip-vip-mu-plugins` will make the command fail.

Example:

```shell
composer vip --local --update-vip-mu-plugins
```


## Copyright (c) 2020 Inpsyde GmbH
This software is only to be used for the specific project for which it was transmitted.! Since it's released under the GIL License you arent allowed to publish or for commercial useage.
