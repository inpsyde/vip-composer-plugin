<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledFilesystemRepository;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryInterface;

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
        /** @var InstalledFilesystemRepository $localRepo */
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        $key = spl_object_hash($localRepo);

        if (!empty(self::$cache[$key])) {
            return self::$cache[$key];
        }

        /** @var InstalledFilesystemRepository $localRepo */
        $localRepo = $this->composer->getRepositoryManager()->getLocalRepository();

        // First we collect all dev packages and recursively their requires
        $devRequires = $this->composer->getPackage()->getDevRequires();
        list($devPackages, $devNames) = $this->findPackagesRecursive($devRequires, $localRepo);

        // Then we collect all non-dev packages and recursively their requires
        $requires = $this->composer->getPackage()->getRequires();
        /** @var $noDevPackages PackageInterface[] */
        list($noDevPackages, $noDevNames) = $this->findPackagesRecursive($requires, $localRepo);

        // After that, we remove from dev packages any package that is also non-dev
        $devPackages = array_diff_key($devPackages, $noDevPackages);
        $devNames = array_diff_key($devNames, $noDevNames);

        $noDevRepo = new InstalledArrayRepository();
        $devRepo = new InstalledArrayRepository();

        // then we fill repositories
        foreach ($devPackages as $devPackage) {
            $devRepo->addPackage($this->replaceRepository($devPackage, $devRepo));
        }
        foreach ($noDevPackages as $noDevPackage) {
            $name = $noDevPackage->getName();
            // This plugin repo is not in dev requirement, but must be considered as it would be.
            if ($name === Plugin::NAME) {
                $devPackage = $this->replaceRepository($noDevPackage, $devRepo);
                unset($noDevPackages[$name], $noDevNames[$name]);
                $devPackages[$name] = $devPackage;
                $devNames[$name] = $name;
                $devRepo->addPackage($this->replaceRepository($devPackage, $devRepo));
                continue;
            }
            $noDevRepo->addPackage($this->replaceRepository($noDevPackage, $noDevRepo));
        }

        self::$cache[$key] = compact(
            'devPackages',
            'devNames',
            'devRepo',
            'noDevPackages',
            'noDevNames',
            'noDevRepo'
        );

        return self::$cache[$key];
    }

    /**
     * @param array $requires
     * @param RepositoryInterface $allRepo
     * @param array $packages
     * @param array $names
     * @return string[]
     */
    private function findPackagesRecursive(
        array $requires,
        RepositoryInterface $allRepo,
        array $packages = [],
        array $names = []
    ): array {

        foreach ($requires as $link) {
            $package = $allRepo->findPackage($link->getTarget(), '*');
            if (!$package) {
                continue;
            }

            $name = $package->getName();
            if (array_key_exists($name, $packages)) {
                continue;
            }

            $packages[$name] = $package;
            $names[$name] = $name;
            list($packages, $names) = $this->findPackagesRecursive(
                $package->getRequires(),
                $allRepo,
                $packages,
                $names
            );
        }

        return [$packages, $names];
    }

    /**
     * Replace a repository in a package.
     * This is necessary to fill this class repo, because a package can be attached to on repo only.
     *
     * @param PackageInterface $package
     * @param RepositoryInterface $repository
     * @return PackageInterface
     */
    private function replaceRepository(
        PackageInterface $package,
        RepositoryInterface $repository
    ): PackageInterface {

        $old = $package->getRepository();
        $package = clone $package;

        if (!$old) {
            $package->setRepository($repository);

            return $package;
        }

        static $setter;
        $setter or $setter = function (RepositoryInterface $repository) {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->repository = $repository;
        };

        $bind = \Closure::bind($setter, $package, get_class($package));
        $bind($repository);

        return $package;
    }
}
