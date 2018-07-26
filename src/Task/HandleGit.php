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
     * @var Config
     */
    private $config;

    /**
     * @var VipDirectories
     */
    private $directories;

    /**
     * @var InstalledPackages
     */
    private $packages;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Unzipper
     */
    private $unzipper;

    /**
     * @param Config $config
     * @param VipDirectories $directories
     * @param InstalledPackages $packages
     * @param Filesystem $filesystem
     * @param Unzipper $unzipper
     */
    public function __construct(
        Config $config,
        VipDirectories $directories,
        InstalledPackages $packages,
        Filesystem $filesystem,
        Unzipper $unzipper
    ) {

        $this->config = $config;
        $this->directories = $directories;
        $this->packages = $packages;
        $this->filesystem = $filesystem;
        $this->unzipper = $unzipper;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Git syncronization / push';
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
        $success = $isPush
            ? $git->push($taskConfig->gitUrl(), $taskConfig->gitBranch())
            : $git->sync($taskConfig->gitUrl(), $taskConfig->gitBranch());

        if ($success && $isPush && $taskConfig->isLocal()) {
            $io->commentLine('Cleaning up...');
            $this->filesystem->removeDirectory($git->mirrorDir());
        }
    }
}
