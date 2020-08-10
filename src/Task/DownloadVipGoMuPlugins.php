<?php

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
use Composer\Util\ProcessExecutor;
use Inpsyde\VipComposer\Git\GitProcess;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class DownloadVipGoMuPlugins implements Task
{
    public const GIT_URL = 'git@github.com:Automattic/vip-go-mu-plugins.git';

    /**
     * @var VipDirectories
     */
    private $vipDirectories;

    /**
     * @var GitProcess
     */
    private $git;

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
        $this->vipDirectories = $directories;
        $this->filesystem = $filesystem;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Download VIP Go MU plugins';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return ($taskConfig->isLocal() || $taskConfig->forceVipMuPlugins())
            && !$taskConfig->skipCoreUpdate();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $targetDir = $this->vipDirectories->vipMuPluginsDir();
        if (!$taskConfig->forceVipMuPlugins() && $this->alreadyInstalled()) {
            $io->infoLine('VIP Go MU plugins already there, skipping.');

            return;
        }

        $io->infoLine('Pulling VIP Go MU plugins (will take a while)...');

        $this->filesystem->ensureDirectoryExists($targetDir);
        $this->filesystem->emptyDirectory($targetDir, true);
        $this->git = new GitProcess($io, $targetDir);

        $timeout = ProcessExecutor::getTimeout();
        ProcessExecutor::setTimeout(0);
        [, , $outputs] = $this->git->exec('clone  --recursive ' . self::GIT_URL . ' .');
        ProcessExecutor::setTimeout($timeout);

        if (!$this->alreadyInstalled()) {
            $io->errorLine('Failed cloning VIP GO MU plugins repository.');
            $io->lines(Io::ERROR, ...$outputs);
        }
    }

    /**
     * @return bool
     */
    private function alreadyInstalled(): bool
    {
        $targetDir = $this->vipDirectories->vipMuPluginsDir();

        $installed = is_dir($targetDir)
            && file_exists("{$targetDir}/z-client-mu-plugins.php")
            && file_exists("{$targetDir}/.gitmodules")
            && $this->checkSubmodules("{$targetDir}/.gitmodules");

        return $installed;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function checkSubmodules(string $path): bool
    {
        $modules = file($path);
        if (!$modules) {
            return false;
        }

        $base = dirname($path);
        foreach ($modules as $line) {
            if (
                preg_match("~^\s*path =(.+)$~", $line, $matches)
                && !is_dir("{$base}/" . trim($matches[1]))
            ) {
                return false;
            }
        }

        return true;
    }
}
