<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

class Installer extends LibraryInstaller
{
    const SUPPORTED_TYPES = [
        'wordpress-plugin',
        'wordpress-theme',
        'wordpress-muplugin',
    ];

    /**
     * @var VipSkeleton
     */
    private $directories;

    /**
     * @param VipSkeleton $directories
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(VipSkeleton $directories, Composer $composer, IOInterface $io)
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
