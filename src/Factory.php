<?php

/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;

class Factory
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $composerIo;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @param Composer $composer
     * @param IOInterface $composerIo
     */
    public function __construct(
        Composer $composer,
        IOInterface $composerIo
    ) {

        $this->composer = $composer;
        $this->composerIo = $composerIo;
    }

    /**
     * @return Io
     */
    public function io(): Io
    {
        return $this->service(
            Io::class,
            function (): Io {
                return new Io($this->composerIo);
            }
        );
    }

    /**
     * @return Composer
     */
    public function composer(): Composer
    {
        return $this->composer;
    }

    /**
     * @return Config
     */
    public function config(): Config
    {
        return $this->service(
            Config::class,
            function (): Config {
                return new Config($this->composer, getcwd());
            }
        );
    }

    /**
     * @return VipDirectories
     */
    public function vipDirectories(): VipDirectories
    {
        return $this->service(
            VipDirectories::class,
            function (): VipDirectories {
                $directories = new VipDirectories($this->fileSystem(), $this->config());
                $directories->createDirs();

                return $directories;
            }
        );
    }

    /**
     * @return Installer\Installer
     */
    public function installer(): Installer\Installer
    {
        return $this->service(
            Installer\Installer::class,
            function (): Installer\Installer {
                return new Installer\Installer(
                    $this->vipDirectories(),
                    $this->composer,
                    $this->composerIo
                );
            }
        );
    }

    /**
     * @return Utils\InstalledPackages
     */
    public function installedPackages(): Utils\InstalledPackages
    {
        return $this->service(
            Utils\InstalledPackages::class,
            function (): Utils\InstalledPackages {
                return new Utils\InstalledPackages($this->composer);
            }
        );
    }

    /**
     * @return Utils\WpPluginFileFinder
     */
    public function wpPluginFileFinder(): Utils\WpPluginFileFinder
    {
        return $this->service(
            Utils\WpPluginFileFinder::class,
            function (): Utils\WpPluginFileFinder {
                return new Utils\WpPluginFileFinder($this->installer());
            }
        );
    }

    /**
     * @return Utils\Unzipper
     */
    public function unzipper(): Utils\Unzipper
    {
        return $this->service(
            Utils\Unzipper::class,
            function (): Utils\Unzipper {
                return new Utils\Unzipper(
                    $this->composerIo,
                    $this->composer->getConfig()
                );
            }
        );
    }

    /**
     * @return Filesystem
     */
    public function fileSystem(): Filesystem
    {
        return $this->service(
            Filesystem::class,
            static function (): Filesystem {
                return new Filesystem();
            }
        );
    }

    /**
     * @return RemoteFilesystem
     */
    public function remoteFileSystem(): RemoteFilesystem
    {
        return $this->service(
            RemoteFilesystem::class,
            function (): RemoteFilesystem {
                return new RemoteFilesystem(
                    $this->composerIo,
                    $this->composer->getConfig()
                );
            }
        );
    }

    /**
     * @param string $class
     * @param callable $factory
     * @return mixed
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    private function service(string $class, callable $factory)
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }

        return $this->services[$class];
    }
}
