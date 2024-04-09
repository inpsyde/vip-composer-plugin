---
title: Environment Initialization
nav_order: 2
---

# Environment Initialization

This package ships with a custom Composer command, `composer vip`.  Thanks to its multiple arguments and flags, it is the single entry point to access numerous features.

Before executing the command, we have to install all dependencies, including the library itself.



## Installing dependencies

We can start with a "website project" repository having only the `composer.json` presented in the [previous chapter](./001-getting-started.md):

```
├ 📄 composer.json
```

After executing `composer install` we will end up with a folder structure like the following:

```
├ 📄 composer.json
├ 📄 composer.lock
├ 📂 vip
┆   ├ 📂 client-mu-plugins
┆   ┆   ├ 📁 my-vip-muplugin
┆   ┆   ├ 📂 vendor
┆   ┆       ├ 📁 composer
┆   ┆       ├ 📂 inpsyde
┆   ┆       ┆   ├ 📁 vip-composer-plugin
┆   ┆       ├ 📄 autoload.php
┆   ├ 📂 config
┆   ├ 📂 images
┆   ├ 📂 languages
┆   ├ 📂 plugins
┆   ┆   ├ 📁 my-vip-plugin
┆   ├ 📂 themes
┆   ┆   ├ 📁 my-vip-theme
┆   ├ 📂 vip-config 
```

The `/vip` folder and its content (even if most of its folder are empty, right now) resembles very closely the [VIP "skeleton"](https://docs.wpvip.com/wordpress-skeleton/), with all the packages placed in the proper place based on their Composer type. This is just the beginning of how the VIP Composer plugins make out life extremely easy.

The real "magic" happens when running the `composer vip` command.



## The `vip` command main modes

The `composer vip` command provided by this package has several parameters and options, but two are its main scopes:

- **Prepare a local environment for development **
- **Deploy to VIP servers**



### Local development environment

Regarding the local development environment, the package supports two options:

- The [VIP local development environment](https://docs.wpvip.com/vip-local-development-environment/) which is based on Lando (thus Docker) plus a series of utilities as scripts to make the containerized system as much as possible similar to the "online" VIP environments.
- A more low-level approach, where this package takes care of the heavy lifting of configuring the environment from an _application_ point of view, and ends up with a `/public` folder which is ready to be uses as the "webroot" for whatever system consumers might want to use, which could be container-based, good old XAMPP or MAMP, or anything else that can serve web pages executing PHP and MySQL.

The two approaches are documented separately:

- [VIP local development environment](./003-vip-local-dev-env.md)
- [Custom local development environment](./004-custom-local-dev-env.md)



### Deployment to VIP servers

The `/vip` folder that resembles the structure expected by VIP and documented in the [VIP "skeleton"](https://docs.wpvip.com/wordpress-skeleton/), is a folder that is _generated_ and so should be git-ignored. (Side note: this package does not create a `.gitignore` file, but you need one to exclude `/vip` from version control).

However, VIP requires we push to GitHub what we have in that version-control-ignored folder. How can we push to GitHub the content of a folder that is VCS-ignored and whose content is "disposable" and created dynamically?

The short answer is: we type `composer vip --deploy` in our terminal and let this package handle it. The details of what happens when we do that is documented in the [Deployment](./005-deployment.md) chapter. 



### More on the command

The `composer vip` command has several options besides the two main usages introduced above. In-depth documentation can be found in the ["VIP Command"](./006-vip-command.md) chapter.



### Configuration

The `composer vip` command outcome can be customized by configuration placed in the projects' `composer.json`. Learn more about all possible customizations in the ["Plugin Configuration"](./007-plugin-configuration.md) chapter.



## Website configuration

Regardless we execute the `composer vip` command to prepare a local environment or to deploy to VIP servers, this package will always deal with website configuration and create some MU plugins.

These files are not part of the "build" process, but are executed and required to run the website. Learn more in the ["Website Configuration"](./008-website-configuration.md) chapter.