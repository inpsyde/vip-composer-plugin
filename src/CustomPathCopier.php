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

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

class CustomPathCopier
{
    const DEFAULT_CONFIG = [
        Plugin::CUSTOM_MUPLUGINS_KEY => 'mu-plugins',
        Plugin::CUSTOM_PLUGINS_KEY => 'plugins',
        Plugin::CUSTOM_THEMES_KEY => 'themes',
        Plugin::CUSTOM_LANGUAGES_KEY => 'languages',
        Plugin::CUSTOM_IMAGES_KEY => 'images',
    ];

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var array
     */
    private $config;

    /**
     * @param Filesystem $filesystem
     * @param array $extra
     */
    public function __construct(Filesystem $filesystem, array $extra)
    {
        $this->filesystem = $filesystem;
        $config = (array)($extra[Plugin::CUSTOM_PATHS_KEY] ?? []);
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
    }

    /**
     * @param Directories $directories
     * @param IOInterface $io
     */
    public function copy(Directories $directories, IOInterface $io)
    {
        $this->copyCustomPaths(Plugin::CUSTOM_MUPLUGINS_KEY, $directories, $io);
        $this->copyCustomPaths(Plugin::CUSTOM_PLUGINS_KEY, $directories, $io);
        $this->copyCustomPaths(Plugin::CUSTOM_THEMES_KEY, $directories, $io);
        $this->copyCustomPaths(Plugin::CUSTOM_LANGUAGES_KEY, $directories, $io);
        $this->copyCustomPaths(Plugin::CUSTOM_IMAGES_KEY, $directories, $io);

        $index = $directories->pluginsDir() . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * @param string $key
     * @param Directories $directories
     * @param IOInterface $io
     */
    private function copyCustomPaths(string $key, Directories $directories, IOInterface $io)
    {
        list($what, $target, $pattern, $flags) = $this->pathInfoForKey($key, $directories);
        if (!$what || !$target || !$pattern) {
            return;
        }

        $io->write("<info>VIP: Copying custom {$what} to deploy folder...</info>");

        $sourcePaths = glob($pattern, $flags);
        $targetIsMu = $target === $directories->muPluginsDir();
        if (!$targetIsMu) {
            $this->filesystem->emptyDirectory($target, true);
        }

        if ($targetIsMu) {
            $toDelete = glob("{$target}/*.php");
            array_walk($toDelete, [$this->filesystem, 'unlink']);
        }

        foreach ($sourcePaths as $sourcePath) {
            $targetPath = "{$target}/" . basename($sourcePath);
            $this->filesystem->copy($sourcePath, $targetPath)
                ?  $this->write($io, "  - Copied {$sourcePath} to {$targetPath}")
                :  $io->writeError("  - ERROR: Copy from {$sourcePath} to {$targetPath} failed.");
        }
    }

    /**
     * @param string $key
     * @param Directories $directories
     * @return array
     */
    private function pathInfoForKey(string $key, Directories $directories): array
    {
        $path = $directories->basePath() . "/{$this->config[$key]}";
        if (!is_dir($path)) {
            return ['', '', '', null];
        }

        switch ($key) {
            case Plugin::CUSTOM_PLUGINS_KEY:
                $what = 'plugins';
                $target = $directories->pluginsDir();
                $pattern = "{$path}/*.*";
                $flags = GLOB_NOSORT;
                break;
            case Plugin::CUSTOM_THEMES_KEY:
                $what = 'themes';
                $target = $directories->themesDir();
                $pattern = "{$path}/*";
                $flags = GLOB_ONLYDIR|GLOB_NOSORT;
                break;
            case Plugin::CUSTOM_MUPLUGINS_KEY:
                $what = 'MU plugins';
                $target = $directories->muPluginsDir();
                $pattern = "{$path}/*.*";
                $flags = GLOB_NOSORT;
                break;
            case Plugin::CUSTOM_LANGUAGES_KEY:
                $what = 'languages';
                $target = $directories->languagesDir();
                $pattern = "{$path}/*.{mo,po}";
                $flags = GLOB_BRACE|GLOB_NOSORT;
                break;
            case Plugin::CUSTOM_IMAGES_KEY:
                $what = 'images';
                $target = $directories->imagesDir();
                $pattern = "{$path}/*.*";
                $flags = GLOB_BRACE|GLOB_NOSORT;
                break;
            default:
                $what = '';
                $target = '';
                $pattern = '';
                $flags = null;
        }

        return [$what, $target, $pattern, $flags];
    }

    /**
     * @param IOInterface $io
     * @param string $message
     */
    private function write(IOInterface $io, string $message)
    {
        if (!$io->isVerbose()) {
            return;
        }

        $io->write($message);
    }
}
