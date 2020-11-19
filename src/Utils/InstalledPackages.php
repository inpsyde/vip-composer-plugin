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

namespace Inpsyde\VipComposer\Utils;

use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepositoryInterface;

class InstalledPackages
{
    /**
     * @var array[][]
     */
    private static $cache;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @param Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * @return PackageInterface[]
     */
    public function devPackages(): array
    {
        return $this->parse()['devPackages'];
    }

    /**
     * @return string[]
     */
    public function devPackageNames(): array
    {
        return $this->parse()['devNames'];
    }

    /**
     * @return InstalledRepositoryInterface
     */
    public function devRepository(): InstalledRepositoryInterface
    {
        return $this->parse()['devRepo'];
    }

    /**
     * @return PackageInterface[]
     */
    public function noDevPackages(): array
    {
        return $this->parse()['noDevPackages'];
    }

    /**
     * @return string[]
     */
    public function noDevPackageNames(): array
    {
        return $this->parse()['noDevNames'];
    }

    /**
     * @return InstalledRepositoryInterface
     */
    public function noDevRepository(): InstalledRepositoryInterface
    {
        return $this->parse()['noDevRepo'];
    }

    /**
     * @return array
     */
    private function parse(): array
    {
        $locker = $this->composer->getLocker();
        $key = spl_object_hash($locker);

        if (!empty(self::$cache[$key])) {
            return self::$cache[$key];
        }

        self::$cache[$key] = [];

        $lock = $locker->getLockData();
        $loader = new ArrayLoader(null, true);

        $cache = self::$cache[$key];
        $cache = $this->buildCache((array)($lock['packages'] ?? []), false, $cache, $loader);
        $cache = $this->buildCache((array)($lock['packages-dev'] ?? []), true, $cache, $loader);

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
            if (isset($packageData['name'])) {
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
