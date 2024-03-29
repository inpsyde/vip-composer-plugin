<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer;

use Composer\Util\Filesystem;
use Composer\Util\Platform;

class VipDirectories
{
    public const PLUGINS_DIR = 'plugins';
    public const MU_PLUGINS_DIR = 'client-mu-plugins';
    public const THEMES_DIR = 'themes';
    public const LANG_DIR = 'languages';
    public const PHP_CONFIG_DIR = 'vip-config';
    public const YAML_CONFIG_DIR = 'config';
    public const PRIVATE_DIR = 'private';
    public const IMAGES_DIR = 'images';

    private const DIRS = [
        self::PLUGINS_DIR => self::PLUGINS_DIR,
        self::MU_PLUGINS_DIR => self::MU_PLUGINS_DIR,
        self::THEMES_DIR => self::THEMES_DIR,
        self::LANG_DIR => self::LANG_DIR,
        self::PHP_CONFIG_DIR => self::PHP_CONFIG_DIR,
        self::YAML_CONFIG_DIR => self::YAML_CONFIG_DIR,
        self::PRIVATE_DIR => self::PRIVATE_DIR,
        self::IMAGES_DIR => self::IMAGES_DIR,
    ];

    private static bool $created = false;

    private string $targetPath;
    private string $vipMuPluginsPath;
    private string $basePath;

    /**
     * @param Filesystem $filesystem
     * @param Config $config
     */
    public function __construct(private Filesystem $filesystem, Config $config)
    {
        $configData = $config->vipConfig();

        $configTargetPath = (string) $configData[Config::VIP_LOCAL_DIR_KEY];
        $targetPath = Platform::expandPath($configTargetPath);

        $configVipMuPluginsPath = (string) $configData[Config::VIP_MUPLUGINS_LOCAL_DIR_KEY];
        $vipMuPluginsPath = Platform::expandPath($configVipMuPluginsPath);

        $this->basePath = $config->basePath();
        $this->targetPath = $filesystem->normalizePath($targetPath);
        $this->vipMuPluginsPath = $filesystem->normalizePath($vipMuPluginsPath);
    }

    /**
     * @return bool
     */
    public function createDirs(): bool
    {
        if (self::$created) {
            return true;
        }

        $target = "{$this->basePath}/{$this->targetPath}";
        foreach (self::DIRS as $dir) {
            $this->filesystem->ensureDirectoryExists("{$target}/{$dir}");
        }

        self::$created = true;

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
    public function phpConfigDir(): string
    {
        return $this->dir(self::PHP_CONFIG_DIR);
    }

    /**
     * @return string
     */
    public function yamlConfigDir(): string
    {
        return $this->dir(self::YAML_CONFIG_DIR);
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
        return $this->filesystem->normalizePath("{$this->basePath}/{$this->vipMuPluginsPath}");
    }

    /**
     * @return string
     */
    public function targetPath(): string
    {
        return $this->filesystem->normalizePath("{$this->basePath}/{$this->targetPath}");
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        $data = [];
        foreach (self::DIRS as $dir) {
            $data[] = $this->dir($dir);
        }

        return $data;
    }

    /**
     * @param string $which
     * @return string
     */
    private function dir(string $which): string
    {
        $this->createDirs();

        return $this->filesystem->normalizePath(
            "{$this->basePath}/{$this->targetPath}/" . self::DIRS[$which]
        );
    }
}
