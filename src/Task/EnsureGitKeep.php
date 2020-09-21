<?php

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
        $dir = is_dir($dir) ? $this->filesystem->normalizePath($dir) : null;
        if (!$dir) {
            return;
        }

        $finder = (new Finder())
            ->in($dir)
            ->files()
            ->filter(
                function (SplFileInfo $info) use ($dir): bool {
                    $path = $this->filesystem->normalizePath($info->getPathname());

                    return $path !== $this->filesystem->normalizePath("{$dir}/.gitkeep");
                }
            );

        $hasFiles = $finder->count() > 0;
        $hasGitKeep = file_exists(realpath($dir) . '/.gitkeep');

        if (!$hasFiles && !$hasGitKeep) {
            file_put_contents("{$dir}/.gitkeep", "\n");
        } elseif ($hasFiles && $hasGitKeep) {
            @unlink("{$dir}/.gitkeep");
        }
    }
}
