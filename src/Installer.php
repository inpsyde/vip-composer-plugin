<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

/*
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */
class Installer extends LibraryInstaller
{
    private const SUPPORTED_TYPES = [
        'wordpress-plugin',
        'wordpress-theme',
        'wordpress-muplugin',
    ];

    /**
     * @param VipDirectories $directories
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(
        private VipDirectories $directories,
        Composer $composer,
        IOInterface $io
    ) {

        parent::__construct($io, $composer);
    }

    /**
     * @psalm-suppress MissingParamType
     */
    public function supports($packageType)
    {
        return in_array($packageType, self::SUPPORTED_TYPES, true);
    }

    /**
     * @param PackageInterface $package
     * @return string
     */
    public function getInstallPath(PackageInterface $package)
    {
        $names = explode('/', $package->getName());
        $dir = end($names);

        return match ($package->getType()) {
            'wordpress-plugin' => $this->directories->pluginsDir() . "/{$dir}",
            'wordpress-theme' => $this->directories->themesDir() . "/{$dir}",
            'wordpress-muplugin' => $this->directories->muPluginsDir() . "/{$dir}",
            default => '',
        };
    }
}
