<?php

/**
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
    /** @var array<string, object> */
    private array $services = [];

    /**
     * @param Composer $composer
     * @param IOInterface $composerIo
     */
    public function __construct(
        private Composer $composer,
        private IOInterface $composerIo
    ) {
    }

    /**
     * @return Io
     */
    public function io(): Io
    {
        return $this->service(Io::class, fn () => new Io($this->composerIo));
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
        return $this->service(Config::class, fn () => new Config($this->composer, getcwd() ?: '.'));
    }

    /**
     * @return VipDirectories
     */
    public function vipDirectories(): VipDirectories
    {
        return $this->service(
            VipDirectories::class,
            function (): VipDirectories {
                $directories = new VipDirectories($this->filesystem(), $this->config());
                $directories->createDirs();

                return $directories;
            }
        );
    }

    /**
     * @return Installer
     */
    public function installer(): Installer
    {
        return $this->service(
            Installer::class,
            fn () => new Installer($this->vipDirectories(), $this->composer, $this->composerIo)
        );
    }

    /**
     * @return Utils\InstalledPackages
     */
    public function installedPackages(): Utils\InstalledPackages
    {
        return $this->service(
            Utils\InstalledPackages::class,
            fn () => new Utils\InstalledPackages($this->composer)
        );
    }

    /**
     * @return Utils\WpPluginFileFinder
     */
    public function wpPluginFileFinder(): Utils\WpPluginFileFinder
    {
        return $this->service(
            Utils\WpPluginFileFinder::class,
            fn () => new Utils\WpPluginFileFinder($this->installer())
        );
    }

    /**
     * @return Utils\ArchiveDownloaderFactory
     */
    public function archiveDownloaderFactory(): Utils\ArchiveDownloaderFactory
    {
        return $this->service(
            Utils\ArchiveDownloaderFactory::class,
            function (): Utils\ArchiveDownloaderFactory {
                return new Utils\ArchiveDownloaderFactory(
                    $this->io(),
                    $this->composer,
                    $this->filesystem()
                );
            }
        );
    }

    /**
     * @return Utils\HttpClient
     */
    public function httpClient(): Utils\HttpClient
    {
        return $this->service(
            Utils\HttpClient::class,
            fn () => new Utils\HttpClient($this->io(), $this->composer)
        );
    }

    /**
     * @return Utils\Unzipper
     */
    public function unzipper(): Utils\Unzipper
    {
        return $this->service(
            Utils\Unzipper::class,
            fn () => new Utils\Unzipper($this->io(), $this->processExecutor(), $this->filesystem())
        );
    }

    /**
     * @return Filesystem
     */
    public function filesystem(): Filesystem
    {
        return $this->service(Filesystem::class, static fn () => new Filesystem());
    }

    /**
     * @return ProcessExecutor
     */
    public function processExecutor(): ProcessExecutor
    {
        return $this->service(
            ProcessExecutor::class,
            fn () => new ProcessExecutor($this->composerIo)
        );
    }

    /**
     * @return Utils\PackageFinder
     */
    public function packageFinder(): Utils\PackageFinder
    {
        return $this->service(
            Utils\PackageFinder::class,
            function (): Utils\PackageFinder {
                return new Utils\PackageFinder(
                    $this->composer()->getRepositoryManager()->getLocalRepository()
                );
            }
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param callable():T $factory
     * @return T
     */
    private function service(string $class, callable $factory): object
    {
        if (!array_key_exists($class, $this->services)) {
            $this->services[$class] = $factory();
        }
        /** @var T */
        return $this->services[$class];
    }
}
