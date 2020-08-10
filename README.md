# VIP Go Composer Plugin



This package is a Composer plugin to be used in projects to be deployed on [VIP Go platform](https://vip.wordpress.com/documentation/vip-go/) and provides a CLI command with **two different purposes**:

1. ease the setup of a **local environment** based on Composer that is compatible with the VIP Go platform
2. ease the **automatic deploy** of the project on VIP Go



## Quick reference

The package provides a command **`composer vip`** that can be used to both **prepare a local environment**  and **deploy to VIP Go repository**.

Examples:

```shell
composer vip --local                       # prepare local environment

composer vip --deploy --branch="develop"   # deploy to VIP Go repository
```

Deploy command as shown above require some configuration in `composer.json`, at very least the GitHub URL for the repository, that if not present in  `composer.json`  can be passed to the command via the `--git-url` option.

It is important to note that `composer vip` command **must be run *after* composer install|update**.

Here's a one liner to both update Composer dependencies and prepare local environment:

```shell
composer update && composer vip
```

If this is the first time you come here, it is suggested to read below for better understand of what, how and why  this command does what it does.

Skip to [Command reference](#command-reference) section for detailed documentation on the command and its available options.



## Why

VIP Go platform is a managed WordPress hosting that allows to deploy to its server via a Git commit to a repository hosted on GitHub. Different branches means different environments, and master branch is for production.

The repository does not contain the full WordPress folder, but *a sort of* the `/wp-content` folder.

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

To have  **a local environment entirely based on Composer, **where not only plugins/themes/libraries, but also WordPress itself are all installed via Composer, requires additional tasks on top of that:

- the Composer autoloader must be loaded at some early point of the request bootstrap
- the Composer autoloader that is deployed must **not** contain development dependencies
- custom installation paths for WordPress packages (themes/plugins/mu-plugins) must be configured because the standard path provided by [Composer installers](https://github.com/composer/installers) is not the correct one (`/wp-content/...`)
- if any MU plugin is required by Composer there must be in place a "loader" mechanism, because WordPress will not load MU plugins from sub-folders and Composer will install MU plugins in sub-folders.

**The aim of the package is to make all these tasks as simple and straightforward as possible.**



## Prerequisites

For **local** development it is necessary:

- _something_ capable of running PHP 7.1+ and MySql. Being it XAMPP, Mamp, Vagrant, Docker or anything else is not really relevant.
- a DB ready for the website
- an (updated) Git client available on the machine and accessible via the `git` command
- Composer



## Prepare the local installation

A folder on local environment must be dedicated to the project. The *example* structure is something like this:

![folders structure](D:\Desktop\vgcp-readme\folder_001.png)

## Structure in detail

In the structure in the image above **only `composer.json` and `vip-config/vip-config.php` are mandatory**, all the other folders/files are optional.

#### `/config`

The config folder that will contain the YAML files used for domain configuration, as described in [VIP Go documentation](https://wpvip.com/documentation/vip-go/syncing-data-on-vip-go/#domain-mapping-config).

#### `/images`

This folder contains site-wide available images, it is part of the standard [VIP Go codebase](https://github.com/Automattic/vip-go-skeleton/tree/master/images).

#### `/mu-plugins`

This folder contains project-specific MU plugins.
Please note that these are not the VIP Go MU plugins, but MU plugins that are specific to the project.

The standard Inpsyde process requires a different repository for each plugin/theme/library then pulled together via the `composer.json` in this repository. However, each MU plugin is a single PHP file, often composed of a few lines, and put each of them in a separate repository is overkill. This is why `/mu-plugins` folder exists: all the files contained in there will be deployed in the [`client-mu-plugins` folder of VIP Go codebase](https://wpvip.com/documentation/vip-go/managing-plugins/#installing-to-the-client-mu-plugins-directory).

#### `/private`

This folder is the equivalent of the namesake folder in VIP Go codebase. As described in [VIP Go documentation](https://wpvip.com/documentation/vip-go/understanding-your-vip-go-codebase/#using%c2%a0private) this folder exists to contain files that are *not* web accessible but can be accessed by your theme or plugins. Typical use-cases are certificates and key files, etc.

Please note that the private folder on VIP is only readable from PHP and **not writable**.

#### `/themes`

This folder contains project-specific themes.

The standard Inpsyde process requires a different repository for each theme that are then pulled together via the `composer.json`. However, sometimes to put themes and especially child-themes in a separate repository might be overkill. This is why `/themes` can be used: all the files contained in there will be deployed in the `themes` folder of VIP Go codebase, alongside any other theme required via Composer.

#### `/vip-config`

This is where the site configuration is placed. There is a correspondent and namesake folder [in VIP Go codebase](https://github.com/Automattic/vip-go-skeleton/tree/master/vip-config) that is supposed to contain a **vip-config.php** file, that is used to contain PHP configuration constants that in a “normal” installation would go in `wp-config.php`, considering that is not accessible on VIP Go.

#### `/composer.json`

This is where “the magic” happens. [Composer](https://getcomposer.org/) is used to pulling together all the dependencies that will be used in the project.



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
            "php": "7.3"
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
                "version": "^5",
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
                "vip-config-dir": "vip-config",
                "config-dir": "config",
                "private-dir": "private"
            }
        }
    }
}
```

The `config` object is part of Composer schema, and not specific of this plugin. It is shown above for completeness. The `config.platform` is set above to `7.3` because that's the version used on VIP Go platform at the moment of writing, and setting this will help in getting same dependencies that will be deployed even running a different PHP version locally.

Because **all `vip-composer` configuration is optional**, if what shown above is fine for you, **there's no need to add any configuration at all**, because these defaults will be used in absence of configuration.



### Configuration in detail

#### `vip-composer.vip`

One of the things that the command provided by this plugin do is to **create a folder that mirrors the structure of VIP Go repository**. This folder will be located in the root of the project folder.

`vip-composer.vip.local-dir` config controls the name of that folder.

Another thing that the plugin command does is to **download VIP Go MU plugins**, and make them usable in local WordPress installation.

`vip-composer.vip.muplugins-local-dir` configuration controls the name of folder where those MU plugins will be downloaded (inside project root).

#### `vip-composer.git`

This object controls the Git configuration for VIP Go GitHub repository. As shown above, default settings are empty, this requires that both URL and branch must be passed as options to `composer vip` command.

Might be a good idea to at least fill in the URL to avoid having to type long commands.

Note: the **URL must be provided in the HTTPS format** (because easier to validate), even if the command (if possible) will use SSH to interact with GitHub.

#### `vip-composer.wordpress`

When the `composer vip` command runs to setup local environment it **installs WordPress**. This configuration object controls the target location and the version that will be installed.

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

Thanks to `plugins-autoload` setting it is possible to select which plugins will be autoloaded via either a deny-list (list of plugins to don't autoload) or a allow-list (list of plugins to autoload).

Without any setting the default is "autoload everything".

To control which plugins to include it is possible to list them, eventually with `*` as wildcard, e. g.:

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

In the case it is easier to _exclude_ packages from being loaded, it is possible to use the `exclude` key:

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

As shown above in the [Prepare the local installation](#prepare-the-local-installation) section, in the root of the project folder there might be some folder for themes, plugins, MU plugins that can be used to include those things in the same repository of the project, without using a separate repository for them. Moreover, there are folder like `config`, `private` and `images` that can be used to fill the related VIP folder.

Only the `config/vip-config.php` is mandatory all the other folders and their content is optional.

`vip-composer.dev-paths` controls the name of those folders. By default names are the same names used by VIP Go repository, except for `mu-plugins/`  is renamed to `client-mu-plugins/`.



## Folder structure *after* the command is ran

Assuming default configuration and a folder structure like the one shown in the screenshot under the [Prepare the local installation](#prepare-the-local-installation) section, after running:

```shell
composer update && composer vip --local
```

(and waiting for a while) the structure of the project folder will be:

![folder structure after vip command](D:\Desktop\vgcp-readme\folder_002.png)

Files and folder created by the command have been highlighted above in light yellow. Let's list them:

#### `/public`

This is the **project web-root**, and it contains a standard WordPress installation.

*Note: in the image all the WordPress root files except `index.php`  (`wp-load.php`, `wp-login.php`, etc...) have been removed for readability’s sake.*

#### `/uploads`

This folder will contain all the media files uploaded locally in WordPress. The folder is located outside the `/public` folder to make the latter entirely disposable. However, because `/public` folder is the web-root, `/uploads` folder is symlinked to `/public/wp-content/uploads` that is the standard WordPress uploads folder, so as long as WordPress (and the web-server) are concerned uploads can be served correctly.

Especially relevant for Linux users: make sure the folder is writable by the local web-server and PHP.

#### `/vip`

This folder contains the **exact 1:1 representation of the VIP Go repository**. It contains exactly the files that will be pushed to the VIP Go repository for the project. More on this folder in next section.

#### `/vip-go-mu-plugins`

This folder contains [VIP Go MU plugins](https://github.com/Automattic/vip-go-mu-plugins). In the `wp-config.php` file that is also generated, this folder is _already_ configured as the WordPress `WPMU_PLUGIN_DIR` so that locally WordPress will correctly load all the VIP MU plugins.

#### `/composer.lock`

The Composer lock file.

Those familiar with Composer could wonder where the Composer "vendor" folder and the Composer autoload file are located.

The answer is: inside `/vip/client-mu-plugins`. As written above, the `/vip` folder contains exactly what will be pushed to the VIP Git repository, and because for sure we need Composer libraries and autoload online, we need those to be inside the `/vip` folder.

#### `/wp-cli.yml`

[WP CLI config file](https://make.wordpress.org/cli/handbook/references/config/#config-files). It contains a reference to the WordPress installation path, so WP CLI commands can be executed from the project root folder without having to pass the `--path` parameter all the time.

#### `/wp-config.php`

WordPress configuration file for the local environment. This is slightly different from a "standard" `wp-config.php` because it contains
- definition of `WPMU_PLUGIN_DIR` pointing `/vip-go-mu-plugins` as the source of MU plugins.
- a `require` statement to load `vip-config/vip-config.php` simulating what happens in VIP Go

The first time the VIP Go Composer plugin is executed there's the need to fill it with project settings, e. g. DB settings, nonce keys and such.

### `/vip` folder in detail

Let's "zoom" in the `/vip` folder:

![/vip folder internal structure](D:\Desktop\vgcp-readme\folder_003.png)

#### `/client-mu-plugins`

This folder contains the **Composer `/vendor` folder**, including the `autoload.php` file. This is a "standard" Composer vendor folder, and it is located here thanks to the `config.vendor-dir` configuration added in `composer.json`.

This folder also contains **a copy** of all MU plugins available in `/mu-plugins` folder.

Finally, it contains the `__loader.php` file, a MU plugin generated by the `composer vip` command that contains instructions to:

- load the Composer autoload file
- force plugins required via composer to be active by calling [`wpcom_vip_load_plugin`](https://wpvip.com/functions/wpcom_vip_load_plugin/) function as recommended in [VIP documentation](https://wpvip.com/documentation/vip-go/managing-plugins/)

#### `/config`, `/images`, and `/languages`

This folder contain **a copy** of any file located in  the namesake folders under root. In the example above these folders are empty because the correspondent folders under root are empty or do not exist at all.

#### `/plugins`

This folder contain all the plugins that have been required via Composer (in the example, just one), plus **a copy** of any plugin located in `/plugins` folder under  root (in the example that folder does not exist at all).

#### `/private`

This folder will contain **a copy** of any file located in `/private` folder under root (in the example that folder does not exist at all) plus a `deploy-id` file, a text file that contains a random UUID that is unique per build (and so per deployment). It can be used by application code, for example, to bust caches so that at every deploy caches are invalidated.

#### `/themes`

This folder contain all the themes that have been required via Composer (in the example, just one), plus **a copy** of any plugin located in `/themes` folder under  root (in the example there's a child theme).

#### `/vip-config`

This folder contain **a copy** of any file located in `/vip-config` folder under root, at the very least the `vip-config.php` file that is required.

### Dev paths

In the section right above has been said how the folders `/client-mu-plugins`, `/config`, `/images`, `/languages`, `/plugins`, `/themes`, and `/vip-config`, all might contain **copies** of files and folders located at the root of the package. The reason for this is to make the `/vip` folder an exact 1:1 representation of what will be deployed to VIP, but there are some gotchas associated with this approach.

Make sure to review the section [Managing Dev Paths](#managing-dev-paths) below that documents this matter in detail. 




### A `.gitignore` sample

It is important that **new files generated by both Composer and this plugins needs to be git-ignored**.

An example of `.gitignore` could be the following:

```shell
/public/
/uploads/
/vip/
/vip-go-mu-plugins/
/wp-config.php
```

but note that this is **not** generated automatically.



## Local Development Workflow

Having a local environment up and running is just the start of the development process.

The workflow of development will be:

1. create a new repository for each theme/plugin/library that has to be used in the project
2. push it to a remote VCS service (GitHub, BitBucket, GitLab...)
3. add it as a dependency to the project's `composer.json`
4. run **`composer update && composer vip --local`** to update the dependencies and refresh the local environment

Having to deal with *remote* repositories for each plugin/theme/library might be overkill, especially in the very early stages of development.

### Local Development with Composer Studio

It is much better to deal with _local_ repositories during development. It has been said how for simple and/or project specific MU plugins/plugins/themes it is possible to use "dev paths" for the scope, but very likely there will be the need to work on packages that need to live in separate repositories.

Luckily Composer supports ["path repositories"](https://getcomposer.org/doc/05-repositories.md#path).

In short, instead of using a VCS repository "as source" for a package, Composer can use a local path (even relative) so that it is possible to run `composer install` and get the content of that folder installed just like any other remotely-hosted package.

The nice thing about it is that the local folder used as path repository could be very well the local clone of a remote repository, so that when changes are finalized and tested locally (thanks to path repository) they can be pushed to the remote repository to have them deployed online.

The bad thing about it is that using path repositories requires changing the `composer.json` and that's not an option, because it does not make sense to place local paths in a `composer.json` that is pushed online.

To solve this problem it is possible to make use of [Composer Studio](https://github.com/franzliedke/studio), a Composer plugin that allows the usage of Composer path repositories without changing the `composer.json`, but making use of a separate **`studio.json`** file that can be easily git-ignored and so kept local.



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

This could be achieved via the `--push` option. Using this option in combination with `--local` is different than just using `--deploy` by the fact that the latter will only run tasks that are relevant for _remote_ server (e. g. always skipping WP install, VIP MU plugins download...) so it is a good choice to deploy from a CI. By using `--local --push` the command will update the development environment  and **also** push changes to remote repo.

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

Example:

```shell
composer vip --sync-dev-paths
```

Considering this is a quite fast operation it makes sense to use (if possible) IDE feature that automatically run the above command when anything changes inside "dev paths".

Here's the screenshot on how this could be set up via in [PHPStorm using a "file watcher"](https://www.jetbrains.com/help/phpstorm/settings-tools-file-watchers.html):

![File watcher setup in PHPStorm](https://d1fqb1zktzfwq.cloudfront.net/public/inpsyde/vip-go-website-template-readme/php_storm.png)



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

The most time consuming task the first time command is ran is to download VIP Go MU plugins. Those plugins accounts for over 300 Mb in total, pulled from a repo with _several_ recursive sub-modules.

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

This software is released under [MIT license](https://opensource.org/licenses/MIT).
