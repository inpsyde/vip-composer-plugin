<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class EnsureGitKeep implements Task
{
    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @param VipDirectories $directories
     */
    public function __construct(VipDirectories $directories)
    {
        $this->directories = $directories;
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
            if (!is_file($maybeFile)) {
                continue;
            }

            $isGitKeep = basename($maybeFile) === '.gitkeep';
            if ($isGitKeep && $hasFiles) {
                $hasGitKeep = true;
                break;
            } elseif ($isGitKeep) {
                $hasGitKeep = true;
                continue;
            }

            $hasFiles = true;
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
}
