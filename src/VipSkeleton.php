<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the vip-composer-plugin package.
 *
 * (c) © 2018 UEFA. All rights reserved.
 */

declare(strict_types=1);

namespace Uefa\VipComposer;

use Composer\Util\Filesystem;
use Composer\Util\Platform;

class VipSkeleton
{

    const PLUGINS_DIR = 'plugins';
    const MU_PLUGINS_DIR = 'client-mu-plugins';
    const THEMES_DIR = 'themes';
    const LANG_DIR = 'languages';
    const CONFIG_DIR = 'vip-config';
    const PRIVATE_DIR = 'private';
    const IMAGES_DIR = 'images';

    const DIRS = [
        self::PLUGINS_DIR => self::PLUGINS_DIR,
        self::MU_PLUGINS_DIR => self::MU_PLUGINS_DIR,
        self::THEMES_DIR => self::THEMES_DIR,
        self::LANG_DIR => self::LANG_DIR,
        self::CONFIG_DIR => self::CONFIG_DIR,
        self::PRIVATE_DIR => self::PRIVATE_DIR,
        self::IMAGES_DIR => self::IMAGES_DIR,
    ];

    private static $created = false;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $targetPath;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @param Filesystem $filesystem
     * @param string $targetPath
     * @param string $basePath
     */
    public function __construct(Filesystem $filesystem, string $targetPath, string $basePath)
    {
        $this->filesystem = $filesystem;
        $this->targetPath = $filesystem->normalizePath($targetPath);
        $this->basePath = $filesystem->normalizePath($basePath);
    }

    /**
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function createDirs(): bool
    {
        $target = "{$this->basePath}/{$this->targetPath}";
        foreach (self::DIRS as $dir) {
            $this->filesystem->ensureDirectoryExists("{$target}/{$dir}");
            if (!file_exists("{$target}/{$dir}/.gitkeep")) {
                $hasFiles = glob("{$target}/{$dir}/*.*");
                $hasFiles or file_put_contents("{$target}/{$dir}/.gitkeep", '');
            }
        }

        return true;
    }

    /**
     * @return string
     */
    public function pluginsDir(): string
    {
        return $this->dir(self::PLUGINS_DIR);
    }

    /**
     * @return string
     */
    public function muPluginsDir(): string
    {
        return $this->dir(self::MU_PLUGINS_DIR);
    }

    /**
     * @return string
     */
    public function themesDir(): string
    {
        return $this->dir(self::THEMES_DIR);
    }

    /**
     * @return string
     */
    public function languagesDir(): string
    {
        return $this->dir(self::LANG_DIR);
    }

    /**
     * @return string
     */
    public function configDir(): string
    {
        return $this->dir(self::CONFIG_DIR);
    }

    /**
     * @return string
     */
    public function privateDir(): string
    {
        return $this->dir(self::PRIVATE_DIR);
    }

    /**
     * @param string $contentDir
     */
    public function symlink(string $contentDir)
    {
        $this->filesystem->ensureDirectoryExists($contentDir);
        $this->filesystem->emptyDirectory($contentDir);

        $map = [
            $this->pluginsDir() => "{$contentDir}/plugins",
            $this->muPluginsDir() => "{$contentDir}/mu-plugins",
            $this->themesDir() => "{$contentDir}/themes",
            $this->languagesDir() => "{$contentDir}/languages",
        ];

        $windows = Platform::isWindows();

        foreach ($map as $target => $link) {
            if ($windows) {
                $this->filesystem->junction($target, $link);
                continue;
            }

            $this->filesystem->relativeSymlink($target, $link);
        }
    }

    /**
     * @return string
     */
    public function targetPath(): string
    {
        return "{$this->basePath}/{$this->targetPath}";
    }

    /**
     * @param string $which
     * @return string
     */
    private function dir(string $which): string
    {
        self::$created or self::$created = $this->createDirs();

        return "{$this->basePath}/{$this->targetPath}/" . self::DIRS[$which];
    }
}
