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
use Composer\Util\ProcessExecutor;

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
     * @var array<string, object>
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
        /** @var Io $io */
        $io = $this->service(
            Io::class,
            function (): Io {
                return new Io($this->composerIo);
            }
        );

        return $io;
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
        /** @var Config $config */
        $config = $this->service(
            Config::class,
            function (): Config {
                return new Config($this->composer, getcwd() ?: '.');
            }
        );

        return $config;
    }

    /**
     * @return VipDirectories
     */
    public function vipDirectories(): VipDirectories
    {
        /** @var VipDirectories $vipDirectories */
        $vipDirectories = $this->service(
            VipDirectories::class,
            function (): VipDirectories {
                $directories = new VipDirectories($this->filesystem(), $this->config());
                $directories->createDirs();

                return $directories;
            }
        );

        return $vipDirectories;
    }

    /**
     * @return Installer
     */
    public function installer(): Installer
    {
        /** @var Installer $installer */
        $installer = $this->service(
            Installer::class,
            function (): Installer {
                return new Installer(
                    $this->vipDirectories(),
                    $this->composer,
                    $this->composerIo
                );
            }
        );

        return $installer;
    }

    /**
     * @return Utils\InstalledPackages
     */
    public function installedPackages(): Utils\InstalledPackages
    {
        /** @var Utils\InstalledPackages $installedPackages */
        $installedPackages = $this->service(
            Utils\InstalledPackages::class,
            function (): Utils\InstalledPackages {
                return new Utils\InstalledPackages($this->composer);
            }
        );

        return $installedPackages;
    }

    /**
     * @return Utils\WpPluginFileFinder
     */
    public function wpPluginFileFinder(): Utils\WpPluginFileFinder
    {
        /** @var Utils\WpPluginFileFinder $wpPluginFileFinder */
        $wpPluginFileFinder = $this->service(
            Utils\WpPluginFileFinder::class,
            function (): Utils\WpPluginFileFinder {
                return new Utils\WpPluginFileFinder($this->installer());
            }
        );

        return $wpPluginFileFinder;
    }

    /**
     * @return Utils\ArchiveDownloaderFactory
     */
    public function archiveDownloaderFactory(): Utils\ArchiveDownloaderFactory
    {
        /** @var Utils\ArchiveDownloaderFactory $archiveDownloaderFactory */
        $archiveDownloaderFactory = $this->service(
            Utils\ArchiveDownloaderFactory::class,
            function (): Utils\ArchiveDownloaderFactory {
                return Utils\ArchiveDownloaderFactory::new(
                    $this->io(),
                    $this->composer,
                    $this->processExecutor(),
                    $this->filesystem()
                );
            }
        );

        return $archiveDownloaderFactory;
    }

    /**
     * @return Utils\HttpClient
     */
    public function httpClient(): Utils\HttpClient
    {
        /** @var Utils\HttpClient $httpClient */
        $httpClient = $this->service(
            Utils\HttpClient::class,
            function (): Utils\HttpClient {
                return Utils\HttpClient::new($this->io(), $this->composer);
            }
        );

        return $httpClient;
    }

    /**
     * @return Utils\Unzipper
     */
    public function unzipper(): Utils\Unzipper
    {
        /** @var Utils\Unzipper $unzipper */
        $unzipper = $this->service(
            Utils\Unzipper::class,
            function (): Utils\Unzipper {
                return new Utils\Unzipper(
                    $this->io(),
                    $this->processExecutor(),
                    $this->filesystem()
                );
            }
        );

        return $unzipper;
    }

    /**
     * @return Filesystem
     */
    public function filesystem(): Filesystem
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->service(
            Filesystem::class,
            static function (): Filesystem {
                return new Filesystem();
            }
        );

        return $filesystem;
    }

    /**
     * @return ProcessExecutor
     */
    public function processExecutor(): ProcessExecutor
    {
        /** @var ProcessExecutor $processExecutor */
        $processExecutor = $this->service(
            ProcessExecutor::class,
            function (): ProcessExecutor {
                return new ProcessExecutor($this->composerIo);
            }
        );

        return $processExecutor;
    }

    /**
     * @param string $class
     * @param callable():object $factory
     * @return object
     *
     * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
     */
    private function service(string $class, callable $factory): object
    {
        // phpcs:enable Inpsyde.CodeQuality.ReturnTypeDeclaration

        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }

        return $this->services[$class];
    }
}
