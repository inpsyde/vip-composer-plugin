<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Git\VipGit;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\Utils\InstalledPackages;
use Inpsyde\VipComposer\Utils\Unzipper;
use Inpsyde\VipComposer\VipDirectories;

class HandleGit implements Task
{
    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param InstalledPackages $packages
     * @param Filesystem $filesystem
     * @param Unzipper $unzipper
     */
    public function __construct(
        private Config $config,
        private VipDirectories $directories,
        private InstalledPackages $packages,
        private Filesystem $filesystem,
        private Unzipper $unzipper
    ) {
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Git synchronization / push';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isGit();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $git = new VipGit(
            $io,
            $this->config,
            $this->directories,
            $this->packages,
            $this->filesystem,
            $this->unzipper
        );

        $isPush = $taskConfig->isGitPush();
        $gitUrl = $taskConfig->gitUrl();
        $gitBranch = $taskConfig->gitBranch();
        $success = $isPush ? $git->push($gitUrl, $gitBranch) : $git->sync($gitUrl, $gitBranch);

        if (!$success) {
            $format = $isPush
                ? 'Failed Git merge and push to %s (%s).'
                : 'Failed Git merge with %s (%s).';

            throw new \RuntimeException(sprintf($format, $gitUrl ?? '', $gitBranch ?? '') ?: '');
        }

        if ($isPush && $taskConfig->isLocal()) {
            $io->commentLine('Cleaning up...');
            $this->filesystem->removeDirectory($git->mirrorDir());
        }
    }
}
