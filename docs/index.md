---
title: Home
nav_exclude: true
---

# Syde VIP Composer Plugin



## Why Bother

When working with [wp.com VIP hosting](https://wpvip.com/) the way we do deployments is making a push to a GitHub repository in the [VIP's GitHub organization](https://github.com/wpcomvip).

That repository represents an _artifact_ of the code we want to put on VIP servers, and thus includes any third party code, output of building tools, and so forth. In a nutshell, it's like we are using GitHub repository more as a "remote filesystem"  than as a real version control repository.

Even if this is better than the plain-old FTP deployments (it's still git, we can see history, revert, fork...) it is also not ideal, for a development point of view, to mix in the same repository first and third part code, built outputs, etc.

Moreover, modern WordPress development can't ignore Composer exists, and that the best way to handle Composer in WordPress is as "website level" and not in single plugins/themes.

The problem this package aims at solving is: **enable "website projects" Composer packages that can be used for development, while also being suitable for local development environments and, at the same time, to deploy to VIP**.



## Documentation (v3)

- [Getting Started](./001-getting-started.md)
- [Environment Initialization](./002-environment-initialization.md)
- [VIP Local Development Environment](./003-vip-local-dev-env.md)
- [Custom Local Development Environment](./004-custom-local-dev-env.md)
- [The `composer vip` Command](./006-vip-command.md)
- [Plugin Configuration](./007-plugin-configuration.md)
- [Website Configuration](./008-website-configuration.md)
- [Application Helpers](./009-application-helpers.md)
- [Application MU Plugins](./010-application-mu-plugins.md)
- [Managing Dev Paths](./011-managing-dev-paths.md)
- [WP and MU Plugins command flags](./012-wp-mu-plugins-command-flags.md)
- [Sunrise Configuration](./013-sunrise-configuration.md)
- [Disable 2FA for Automated Tests](./014-disable-2fa-automatest-tests.md)



## Dependencies and Minimum Requirements

The plugin requires **PHP 8.0+** and **Composer 2.4+**.

There are no more production dependencies. When installed as root package with dev dependencies, this package directly requires:

- [Brain Monkey](https://giuseppe-mazzapica.gitbook.io/brain-monkey) (MIT)
- [Composer](https://getcomposer.org/) (MIT)
- [Inpsyde PHP Coding Standards](https://github.com/inpsyde/php-coding-standards) (MIT)
- [Inpsyde WP Stubs](https://github.com/inpsyde/wp-stubs) (MIT)
- [PHPUnit](https://phpunit.de/index.html) (BSD-3 Clause)
- [Psalm](https://psalm.dev/) (MIT)



## License and Copyright

_VIP Composer Plugin_ is a free software, and is released under the terms of the MIT license.
See [LICENSE](https://github.com/inpsyde/vip-composer-plugin/blob/master/LICENSE) for complete license.

The team at Syde is engineering the Web since 2006.
