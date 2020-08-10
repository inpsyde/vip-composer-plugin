<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class EnsureGitKeep implements Task
{

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param VipDirectories $directories
     * @param Filesystem $filesystem
     */
    public function __construct(VipDirectories $directories, Filesystem $filesystem)
    {
        $this->directories = $directories;
        $this->filesystem = $filesystem;
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
        return true;
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
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $hasFiles = false;
        $hasGitKeep = false;

        foreach (glob("{$dir}/{*,.*}", GLOB_NOSORT | GLOB_BRACE) as $maybeFile) {
            $basename = $maybeFile ? basename($maybeFile) : '';
            if (
                !$basename
                || $basename === '.'
                || $basename === '..'
                || $this->filesystem->isSymlinkedDirectory($maybeFile)
            ) {
                continue;
            }

            if (is_dir($maybeFile)) {
                $hasFiles = $this->isNotEmptyDir($maybeFile);
                // Remove empty subdirectories.
                $hasFiles or $this->filesystem->removeDirectory($maybeFile);
                continue;
            }

            if (!is_file($maybeFile)) {
                continue;
            }

            $isGitKeep = basename($maybeFile) === '.gitkeep';
            $isGitKeep and $hasGitKeep = true;
            if ($isGitKeep && $hasFiles) {
                break;
            }

            $hasFiles = !$isGitKeep;
            if ($hasGitKeep) {
                break;
            }
        }

        if (!$hasFiles && !$hasGitKeep) {
            file_put_contents("{$dir}/.gitkeep", "\n");
        } elseif ($hasFiles && $hasGitKeep) {
            @unlink("{$dir}/.gitkeep");
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isNotEmptyDir(string $path): bool
    {
        $realpath = realpath($path);
        $dirs = [];
        foreach (glob("{$realpath}/{*,.*}", GLOB_NOSORT | GLOB_BRACE) as $contentItem) {
            $base = $contentItem ? basename($contentItem) : '';
            if (!$base || $base === '.' || $base === '..') {
                continue;
            }

            if (is_file($contentItem)) {
                return true;
            }

            if (is_dir($contentItem)) {
                $dirs[] = $contentItem;
            }
        }

        while ($dirs) {
            $path = array_shift($dirs);
            if ($this->isNotEmptyDir($path)) {
                return true;
            }
        }

        return false;
    }
}
