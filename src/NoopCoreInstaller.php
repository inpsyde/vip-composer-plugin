<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Installer\InstallerInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use InvalidArgumentException;

/**
 * The plugin this class is part of has the only aim to download WordPress as a zip artifact,
 * without considering it a Composer package at all.
 *
 * This workflow is probably going to cause issues and conflicts if other packages require
 * WordPress as a Composer package.
 *
 * Since Composer does not have a public official API to remove packages "on the fly", the way we
 * could obtain that by using Composer script events are hackish and complicated.
 * The easiest solution I found is to replace the installer for 'wordpress-core' packages with
 * a custom installer (this class) that just does... nothing.
 * So Composer will think that WordPress package is installed and will not complain,
 * but nothing really happened.
 */
class NoopCoreInstaller implements InstallerInterface
{

    /**
     * @var IOInterface;
     */
    private $io;

    /**
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return $packageType === 'wordpress-core';
    }

    /**
     * Just return true, because we don't want Composer complain about WordPress not being installed.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return true;
    }

    /**
     * Do nothing. Just inform user that we are skipping the package installation.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $name = $package->getName();
        $this->io->write("\<comment>VIP: Skipping installation of {$name}...</comment>\n");
    }

    /**
     * Do nothing. Just inform user that we are skipping the package installation.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $initial already installed package version
     * @param PackageInterface $target updated version
     *
     * @throws InvalidArgumentException if $initial package is not installed
     */
    public function update(
        InstalledRepositoryInterface $repo,
        PackageInterface $initial,
        PackageInterface $target
    ) {
        // do nothing
        $name = $target->getName();
        $this->io->write("\n<comment>VIP: Skipping updating of {$name}...</comment>\n");
    }

    /**
     * We don't support uninstall, at the moment.
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // do nothing
    }

    /**
     * We return an existing and valid path to prevent error output or installation abortion in case
     * Composer checks it exists.
     *
     * @param  PackageInterface $package
     * @return string           path
     */
    public function getInstallPath(PackageInterface $package)
    {
        return getcwd();
    }
}
