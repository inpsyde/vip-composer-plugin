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
     * @var mixed
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
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param Config $config
     * @param IOInterface $io
     */
    public function __construct(Config $config, IOInterface $io)
    {
        $vendor = $config->get('vendor-dir');
        $this->filesystem = new Filesystem();
        $this->target = $this->filesystem->normalizePath(dirname($vendor));
        $this->base = basename($vendor);
        $this->bin = $this->filesystem->normalizePath($config->get('bin-dir'));
        $this->io = $io;
    }

    /**
     * @param array $paths
     */
    public function build(array $paths)
    {
        $ignore = [];
        $currentIgnore = [];
        if (file_exists("{$this->target}/.gitignore")) {
            $this->io->write('<comment>VIP: found .gitignore for vendors.</comment>');
            $currentIgnore = file("{$this->target}/.gitignore", FILE_IGNORE_NEW_LINES);
            $ignore = $currentIgnore;
        }

        foreach ($paths as $path) {
            $toIgnore = "/{$this->base}/{$path}/";
            in_array($toIgnore, $ignore, true) or $ignore[] = $toIgnore;
        }

        $bin = $this->binToIgnore();
        $bin and $ignore[] = $bin;

        $ignore[] = "/{$this->base}/composer/";
        $ignore[] = "/{$this->base}/autoload.php";

        $ignore = array_unique(array_filter($ignore));

        if ($ignore !== $currentIgnore) {
            $this->io->write('<comment>VIP: updating .gitignore...</comment>');
            file_put_contents("{$this->target}/.gitignore", implode("\n", $ignore));
        }
    }

    /**
     * @return string
     */
    private function binToIgnore(): string
    {
        if (strpos($this->bin, $this->target) !== 0) {
            return '';
        }

        $shortest = $this->filesystem->findShortestPath($this->target, $this->bin, true);
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
}
