<?php

/**
 * This file is part of the vip-composer-plugin package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class EnsureGitKeep implements Task
{
    /**
     * @param VipDirectories $directories
     * @param Filesystem $filesystem
     */
    public function __construct(
        private VipDirectories $directories,
        private Filesystem $filesystem
    ) {
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Ensure .gitkeep';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return !$taskConfig->isVipDevEnv();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        foreach ($this->directories->toArray() as $dir) {
            $this->ensureGitKeepFor($dir);
        }

        $io->infoLine('Done.');
    }

    /**
     * @param string $dir
     * @return void
     */
    private function ensureGitKeepFor(string $dir): void
    {
        $dir = is_dir($dir) ? $this->filesystem->normalizePath($dir) : null;
        if (($dir === null) || ($dir === '')) {
            return;
        }

        $hasFiles = $this->haveFiles(0, $dir);
        $hasGitKeep = file_exists(((string) realpath($dir)) . '/.gitkeep');

        if (!$hasFiles && !$hasGitKeep) {
            file_put_contents("{$dir}/.gitkeep", "\n");
        } elseif ($hasFiles && $hasGitKeep) {
            @unlink("{$dir}/.gitkeep");
        }
    }

    /**
     * @param int $depth
     * @param string ...$dirs
     * @return bool
     */
    private function haveFiles(int $depth, string ...$dirs): bool
    {
        if (!$dirs) {
            return false;
        }

        /** @var array<string> $newDirs */
        $newDirs = [];
        foreach ($dirs as $dir) {
            if ($this->hasFiles($dir, $newDirs, $depth)) {
                return true;
            }
        }

        /** @psalm-suppress MixedArgument */

        return $this->haveFiles($depth + 1, ...$newDirs);
    }

    /**
     * @param string $dir
     * @param array $dirs
     * @param int $depth
     * @return bool
     */
    private function hasFiles(string $dir, array &$dirs, int $depth): bool
    {
        $finder = Finder::create()
            ->in($dir)
            ->depth('== 0')
            ->files()
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->filter(
                static function (SplFileInfo $info) use ($depth): bool {
                    return $depth > 0 || $info->getBasename() !== '.gitkeep';
                }
            );

        if ($finder->count() > 0) {
            return true;
        }

        $finder = Finder::create()->in($dir)->depth('== 0')->directories();
        /** @var SplFileInfo $splFileInfo */
        foreach ($finder as $splFileInfo) {
            $dirs[] = $splFileInfo->getPathname();
        }

        return false;
    }
}
