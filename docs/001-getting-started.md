---
title: Getting Started
nav_order: 1
---

# Getting Started



##  The "website project" repository

When working with wp.com VIP, the first thing we need to create is a repository for the "website" project. From a Composer point of view, this is the root package of type `project`.

This repository will be:

- The central place to **require all dependencies** via Composer. "Dependencies", means themes, plugins, MU plugins, libraries.
- The starting point to **set up the local development environment** for the website
- The **gateway to deploy** to the VIP GitHub repository, and thus to VIP servers.



## It's all a `composer.json`

All the three high-level features expected from the "website project" repository and listed above are mostly done by creating a `composer.json` that requires this package as a dependency.

```json
{
    "name": "acme/my-vip-website",
    "description": "Central repository for My VIP website.",
    "license": "proprietary",
    "type": "project",
    "require": {
        "inpsyde/vip-composer-plugin": "^3",
        "acme/my-vip-theme": "^1",
        "acme/my-vip-plugin": "^1",
        "acme/my-vip-muplugin": "^1"
    },
    "autoload": {
        "exclude-from-classmap": [
            "**/vip-composer-plugin/**"
        ]
    },
    "config": {
        "vendor-dir": "vip/client-mu-plugins/vendor",
        "optimize-autoloader": true,
        "allow-plugins": {
            "composer/*": true,
            "inpsyde/*": true
        }
    }
}
```

Having _just_ such a `composer.json` in the root of the "website project" repository, is **most of the work we need to do** to satisfy all our needs in the regard of the project initialization, local development, and deployment preparation.



### Highlights

- Note `inpsyde/vip-composer-plugin` in `require`. That is the requirement for this package, and that's where the "magic" resides.
- In real-world projects the list of dependencies in `require` will be longer, sometimes _much_ longer, but that is business-as-usual in a Composer package. This example lists just a few example packages.
- The **`config.vendor-dir` is a mandatory configuration** for this package to work. This depends on the folder structure we need to have and because Composer only reads `config` of the root package we need this configuration to happen in the project repository.
- `autoload.exclude-from-classmap` is an optimization of autoloader. The great part of this package contains code that is only used during the "build phase" of the website, still we can't require it as a "dev" dependency, because we also need it when building for production. By excluding this package's classes from the optimized Composer autoloader, the impact on build website as negligible as ~200kb of text files sitting on the hard drive.



## Next steps

With our base `composer.json` in place, we are a `composer install & composer vip` away from having our website project repository ready for local development as we as ready to be deployed. Learn more in the [next chapter](./002-environment-initialization.md).