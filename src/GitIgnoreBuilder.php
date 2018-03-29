<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

class GitIgnoreBuilder
{

    /**
     * @var string
     */
    private $vendor;

    /**
     * @var string
     */
    private $target;

    /**
     * @var string
     */
    private $base;

    /**
     * @var string
     */
    private $bin;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var InstalledPackages
     */
    private $installedPackages;

    /**
     * @param string $binDir
     * @param string $vendorDir
     * @return string
     */
    public static function binToIgnore(string $binDir, string $vendorDir): string
    {
        static $filesystem;
        $filesystem or $filesystem = new Filesystem();

        $target = $filesystem->normalizePath(dirname($vendorDir));
        $binDir = $filesystem->normalizePath($binDir);

        if (strpos($binDir, $target) !== 0) {
            return '';
        }

        $shortest = $filesystem->findShortestPath($target, $binDir, true);
        $isDot = ($shortest[0] ?? '') === '.';
        if ($isDot && ($shortest[1] ?? '') === '.') {
            return '';
        }

        $shortest = $isDot ? ltrim($shortest, '.') : '/' . ltrim($shortest, '/');
        if (!$shortest || !trim($shortest, '/')) {
            return '';
        }

        return rtrim($shortest, '/') . '/';
    }

    /**
     * @param InstalledPackages $installedPackages
     * @param Config $config
     * @param IOInterface $io
     */
    public function __construct(InstalledPackages $installedPackages, Config $config, IOInterface $io)
    {
        $this->installedPackages = $installedPackages;
        $filesystem = new Filesystem();
        $this->vendor = $filesystem->normalizePath($config->get('vendor-dir'));
        $this->bin = $filesystem->normalizePath($config->get('bin-dir'));
        $this->target = dirname($this->vendor);
        $this->base = basename($this->vendor);
        $this->io = $io;
    }

    /**
     * Build .gitignore for VIP
     */
    public function build()
    {
        $ignore = [];
        $currentIgnore = [];
        if (file_exists("{$this->target}/.gitignore")) {
            $this->io->write('<comment>VIP: found .gitignore for vendors.</comment>');
            $currentIgnore = file("{$this->target}/.gitignore", FILE_IGNORE_NEW_LINES);
            $ignore = $currentIgnore;
        }

        $packages = $this->installedPackages->devPackageNames();

        foreach ($packages as $package) {
            $toIgnore = "/{$this->base}/{$package}/";
            in_array($toIgnore, $ignore, true) or $ignore[] = $toIgnore;
        }

        $bin = self::binToIgnore($this->bin, $this->vendor);
        $bin and $ignore[] = $bin;

        $ignore[] = "/{$this->base}/composer/";
        $ignore[] = "/{$this->base}/autoload.php";
        $ignore[] = 'node_modules/';

        $ignore = array_unique(array_filter($ignore));

        if ($ignore !== $currentIgnore) {
            $this->io->write('<comment>VIP: updating .gitignore...</comment>');
            file_put_contents("{$this->target}/.gitignore", implode("\n", $ignore));
        }
    }
}
