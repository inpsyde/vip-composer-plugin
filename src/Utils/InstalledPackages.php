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

namespace Inpsyde\VipComposer\Utils;

use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepositoryInterface;

class InstalledPackages
{
    /** @var array<string, array<string, array|InstalledRepositoryInterface>> */
    private static array $cache = [];

    /**
     * @param Composer $composer
     */
    public function __construct(private Composer $composer)
    {
    }

    /**
     * @return array<PackageInterface>
     */
    public function devPackages(): array
    {
        /** @var array<PackageInterface> $packages */
        $packages = $this->parse()['devPackages'];

        return $packages;
    }

    /**
     * @return array<string>
     */
    public function devPackageNames(): array
    {
        /** @var array<string> $names */
        $names = $this->parse()['devNames'];

        return $names;
    }

    /**
     * @return InstalledRepositoryInterface
     */
    public function devRepository(): InstalledRepositoryInterface
    {
        /** @var InstalledRepositoryInterface $repo */
        $repo = $this->parse()['devRepo'];

        return $repo;
    }

    /**
     * @return array<PackageInterface>
     */
    public function noDevPackages(): array
    {
        /** @var array<PackageInterface> $packages */
        $packages = $this->parse()['noDevPackages'];

        return $packages;
    }

    /**
     * @return array<string>
     */
    public function noDevPackageNames(): array
    {
        /** @var array<string> $names */
        $names = $this->parse()['noDevNames'];

        return $names;
    }

    /**
     * @return InstalledRepositoryInterface
     */
    public function noDevRepository(): InstalledRepositoryInterface
    {
        /** @var InstalledRepositoryInterface $repo */
        $repo = $this->parse()['noDevRepo'];

        return $repo;
    }

    /**
     * @return array<string, array|InstalledArrayRepository> $cache
     */
    private function parse(): array
    {
        $locker = $this->composer->getLocker();
        $key = spl_object_hash($locker);

        if (!empty(self::$cache[$key])) {
            /** @var array<string, array|InstalledArrayRepository> $cache */
            $cache = self::$cache[$key];

            return $cache;
        }

        $lock = $locker->getLockData();
        $loader = new ArrayLoader(null, true);

        $cache = [];
        $cache = $this->buildCache((array) ($lock['packages'] ?? []), false, $cache, $loader);
        $cache = $this->buildCache((array) ($lock['packages-dev'] ?? []), true, $cache, $loader);

        /** @var array<string, array|InstalledArrayRepository> $cache */

        self::$cache[$key] = $cache;

        return $cache;
    }

    /**
     * @param array $data
     * @param bool $dev
     * @param array $cache
     * @param ArrayLoader $loader
     * @return array
     */
    private function buildCache(array $data, bool $dev, array $cache, ArrayLoader $loader): array
    {
        $names = [];
        $packages = [];
        $repo = new InstalledArrayRepository();

        foreach ($data as $packageData) {
            if (is_array($packageData) && isset($packageData['name'])) {
                $package = $loader->load($packageData);
                $names[] = $package->getName();
                $packages[] = $package;
                $repo->addPackage($package);
            }
        }

        $dev ? $cache['devNames'] = $names : $cache['noDevNames'] = $names;
        $dev ? $cache['devPackages'] = $packages : $cache['noDevPackages'] = $packages;
        $dev ? $cache['devRepo'] = $repo : $cache['noDevRepo'] = $repo;

        return $cache;
    }
}
