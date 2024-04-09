---
title: Custom local development environment
nav_order: 4
---

# Custom Local Development Environment

Sometimes we don't want or can't use the [VIP Local Development Environment](https://docs.wpvip.com/vip-local-development-environment/) (e. g. if we want/need to use free Docker alternatives).

In those cases, we don't benefit from some of the heavy lifting done by the VIP tool, but this package can take the burden.
In fact, by executing `composer vip --local` this package does what the VIP tool does at container level:

- Copy/symlink the various folders in the skeleton (and/or setting configuration constants) so that WordPress can find them
- [VIP platform MU plugins](https://docs.wpvip.com/vip-go-mu-plugins/)
- Early loading of [pre-configuration files](https://github.com/Automattic/vip-go-mu-plugins/blob/f80274212ac812f0cc9fc1b0045c69df3673081b/000-pre-vip-config/requires.php#L5)
- Loading of [user configuration](https://docs.wpvip.com/wordpress-skeleton/vip-config-directory/)
- [Handling of `sunrise.php`](https://docs.wpvip.com/wordpress-on-vip/multisites/sunrise-php/#h-configuration-for-local-development)

Besides what it does also when using VIP Local Development Environment:

- Copy the default configuration, helpers, and MU plugins from the library folder to the website project's `/vip` folder.
- Copy environment-specific configuration files from external packages, to the website project's `/vip/vip-config/env` folder.
- Copy "development paths", that is, plugins and themes part of the website project repository itself, into the `/vip` folder.
- Generate a MU plugin that loads MU plugins installed via Composer. In fact, because Composer place MU plugins inside a folder, WordPress don't load them by default. The same MU plugins also takes care of force-activating regular plugins listed in configuration. This practice is [recommended by VIP](https://docs.wpvip.com/plugins/activate-plugins-through-code/).
- Generate a customized version of Composer autoloader specifically designed to be uploaded to VIP. Alongside a MU plugin that loads it.
- Generate a deployment "id" and "version" (if applicable), and save them in two files in website project's `/vip/private` folder.

And it also, to allow running WordPress locally:

- Download WordPress
- Generate and customize a `wp-config.php` file
- Generate a `wp-cli.yml` file
- Deal with local folder structure (e. g. the `/uploads` folder)



## The workflow

- Set up a local environment (whatever that is) to serve content using `/path/to/website-repo/public` as webroot.
- Execute the `composer vip --local` command



### Set Up the Environment

Anything that can run PHP 8.0 and MySQL and can serve web pages to browser is suitable.

Please note that using a custom development environment, keeping "production parity" for server software (WordPress version, PHP, MySQL, web server, Memcached, Elasticsearch, etc...) is up to you. 



### Executing the `vip` Command

To prepare the environment for any custom local development environment, after having installed Composer dependencies, run:

```shell
composer vip --local
```



## First-Time Configuration

The first time this is executed, it will generate a `wp-config.php` in the root folder. Please configure in that file the DB credentials and any other relevant configuration, e. g. define `MULTISITE` constant to true if you need that.

Please note that the command will *not* install WordPress, so on the first visit the WordPress installation screen will be shown. 

It is suggested to use WP CLI to install WordPress right after having executed `composer vip --local`.

When that is done, the environment is ready to be used.



## Media Folder

The command creates an `uploads/` folder in the root of the website repository. It is symlinked from `public/wp-content/uploads` so WordPress will find it.
This makes the generated `vip/` folder entirely disposable, and it can be deleted without losing any locally updated media.



## Public Folder and Web-Server Configuration

The `public/` folder, that must be configured as webroot of whatever web-server we are using is also disposable. If deleted, it will be recreated without issues on the next `composer vip --local` execution.

However, sometimes we might need web-server configuration to be placed in that folder (an `.htaccess` file, for example).
In such cases, the folder is not entirely disposable anymore.
Moreover, we might want to keep such configuration files under version control, while almost certainly we want to ignore from VCS the generated `public/` folder.

One way to solve this issue is to keep such configuration files in the root of the repository (or in a versioned sub-folder), and then use a custom Composer command to copy/symlink that file in place.

For example, in `composer.json`:

```json
{
    "scripts": {
        "vip-local": [
            "composer vip --local",
            "sh cp ./local-config/* ./public/"
        ]
    }
}
```

With that, running `composer vip-local` will copy all files in a `local-config/` folder into `public/`, making the latter disposable and at the same time allowing to easily keep configuration files under version control.




## Version Control

To use the same website repository folder to execute the local environment but also to develop and so to keep the files under version control, it is necessary to use ignore from the VCS in use files and folders generated by the plugin.

An example for a `.gitignore` file could be:

```
/public
/uploads
/vip
/vip-config/env/local.php
/vip-go-mu-plugins
/wp-config.php
/wp-cli.yml
```