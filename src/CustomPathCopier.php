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
    private $extra;

    /**
     * @param Filesystem $filesystem
     * @param string $basePath
     * @param array $extra
     */
    public function __construct(Filesystem $filesystem, string $basePath, array $extra)
    {
        $this->filesystem = $filesystem;
        $this->basePath = $basePath;
        $this->extra = (array)($extra[Plugin::CUSTOM_PATHS_KEY] ?? []);
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
        $validPaths = $this->customPathsForKey($key);
        if (!$validPaths) {
            return;
        }

        list($what, $target, $forceDir) = $this->pathInfoForKey($key, $directories);

        $io->write("<info>VIP: Copying custom {$what} to deploy folder...</info>");

        foreach ($validPaths as $validPath) {
            if ($this->shouldCopyPath($validPath, $forceDir, $io, $what)) {
                $targetPath = "{$target}/" . basename($validPath);
                $this->filesystem->copy($validPath, $targetPath)
                    ?  $this->write($io, "  - Copied {$validPath} to {$targetPath}")
                    :  $io->writeError("  - ERROR: Copy from {$validPath} to {$targetPath} failed.");
            }
        }
    }

    /**
     * @param string $key
     * @param Directories $directories
     * @return array
     */
    private function pathInfoForKey(string $key, Directories $directories): array
    {
        $what = 'plugins';
        $target = null;
        $forceDir = false;
        if ($key === Plugin::CUSTOM_MUPLUGINS_KEY) {
            $what = 'MU plugins';
            $target = $directories->muPluginsDir();
        } elseif ($key === Plugin::CUSTOM_THEMES_KEY) {
            $what = 'themes';
            $target = $directories->themesDir();
            $forceDir = true;
        }

        return [$what, $target ?: $directories->pluginsDir(), $forceDir];
    }

    /**
     * @param string $path
     * @param bool $onlyDir
     * @param IOInterface $io
     * @param string $what
     * @return bool
     */
    private function shouldCopyPath(string $path, bool $onlyDir, IOInterface $io, string $what): bool
    {
        $isDir = is_dir($path);
        if ($onlyDir && !$isDir) {
            $this->write(
                $io,
                sprintf(
                    '    - Skipping "%s" because it is not a directory and only directory are allowed for %s.',
                    $path,
                    $what
                )
            );

            return false;
        }

        if (!$isDir && strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            $this->write($io, "    - Skipping \"{$path}\" because it is a file without PHP extension.");

            return false;
        }

        if (!$isDir) {
            return true;
        }

        if (!glob("{$path}/*.php", GLOB_NOSORT)) {
            $this->write($io, "    - Skipping \"{$path}\" because looks like it contains no PHP files.");

            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @return array
     */
    private function customPathsForKey(string $key): array
    {
        $customPaths = $this->extra[$key] ?? null;
        is_string($customPaths) and $customPaths = [$customPaths];
        if (!$customPaths) {
            return [];
        }

        $validPaths = [];
        foreach ($customPaths as $customPath) {
            if (is_string($customPath)) {
                $validPaths = $this->expandCustomPath($customPath, $validPaths);
            }
        }

        return $validPaths;
    }

    /**
     * @param string $customPath
     * @param array $validPaths
     * @return array
     */
    private function expandCustomPath(string $customPath, array $validPaths): array
    {
        $toLoop = substr_count($customPath, '*')
            ? glob("{$this->basePath}/$customPath")
            : ["{$this->basePath}/$customPath"];

        $valid = array_filter((array)$toLoop, 'is_readable');

        return $valid ? array_merge($validPaths, $valid) : $validPaths;
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
