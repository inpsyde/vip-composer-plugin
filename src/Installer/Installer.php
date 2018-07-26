<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Inpsyde\VipComposer\VipDirectories;

class Installer extends LibraryInstaller
{
    private const SUPPORTED_TYPES = [
        'wordpress-plugin',
        'wordpress-theme',
        'wordpress-muplugin',
    ];

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @param VipDirectories $directories
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(VipDirectories $directories, Composer $composer, IOInterface $io)
    {
        $this->directories = $directories;

        parent::__construct($io, $composer);
    }

    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return in_array($packageType, self::SUPPORTED_TYPES, true);
    }

    /**
     * @inheritdoc
     */
    public function getInstallPath(PackageInterface $package)
    {
        $names = explode('/', $package->getName());
        $dir = array_pop($names);

        switch ($package->getType()) {
            case 'wordpress-plugin':
                return $this->directories->pluginsDir() . "/{$dir}";
            case 'wordpress-theme':
                return $this->directories->themesDir() . "/{$dir}";
            case 'wordpress-muplugin':
                return $this->directories->muPluginsDir() . "/{$dir}";
        }

        return '';
    }
}
