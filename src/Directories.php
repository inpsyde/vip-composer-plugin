<?php # -*- coding: utf-8 -*-
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

use Composer\Util\Filesystem;
use Composer\Util\Platform;

class Directories
{

    const PLUGINS_DIR = 'plugins';
    const MU_PLUGINS_DIR = 'client-mu-plugins';
    const THEMES_DIR = 'themes';
    const LANG_DIR = 'languages';
    const CONFIG_DIR = 'vip-config';
    const PRIVATE_DIR = 'private';
    const IMAGES_DIR = 'images';
    const VIP_GO_MUPLUGINS_DIR = 'vip-go-mu-plugins';

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
     * @return string
     */
    public function imagesDir(): string
    {
        return $this->dir(self::IMAGES_DIR);
    }

    /**
     * @return string
     */
    public function vipMuPluginsDir(): string
    {
        self::$created or self::$created = $this->createDirs();
        $this->filesystem->ensureDirectoryExists("{$this->basePath}/" . self::VIP_GO_MUPLUGINS_DIR);

        return "{$this->basePath}/" . self::VIP_GO_MUPLUGINS_DIR;
    }

    /**
     * @param string $contentDir
     * @param bool $hasVipMu
     */
    public function symlink(string $contentDir, bool $hasVipMu)
    {
        $this->filesystem->ensureDirectoryExists($contentDir);
        $this->filesystem->emptyDirectory($contentDir);

        $map = [
            $this->pluginsDir() => "{$contentDir}/plugins",
            $this->themesDir() => "{$contentDir}/themes",
            $this->languagesDir() => "{$contentDir}/languages",
            $this->muPluginsDir() => $hasVipMu
                ? "{$contentDir}/client-mu-plugins"
                : "{$contentDir}/mu-plugins",
        ];

        if ($hasVipMu) {
            $map[$this->vipMuPluginsDir()] = "{$contentDir}/mu-plugins";
        }

        $windows = Platform::isWindows();

        foreach ($map as $target => $link) {
            if (is_dir($link)) {
                $this->filesystem->removeDirectory($link);
            }

            $this->filesystem->ensureDirectoryExists($target);

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
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
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
