<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Inpsyde\VipComposer\Config;
use Inpsyde\VipComposer\Git\GitProcess;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class DownloadVipGoMuPlugins implements Task
{
    public const GIT_URL = 'https://github.com/Automattic/vip-go-mu-plugins-built.git';

    /**
     * @param Config $config
     * @param VipDirectories $vipDirectories
     * @param Filesystem $filesystem
     */
    public function __construct(
        private Config $config,
        private VipDirectories $vipDirectories,
        private Filesystem $filesystem
    ) {
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
        return $taskConfig->isAnyLocal() && !$taskConfig->skipVipMuPlugins();
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
            $this->copySunrise($io);

            return;
        }

        $io->infoLine('Pulling VIP Go MU plugins (will take a while)...');

        $this->filesystem->ensureDirectoryExists($targetDir);
        $this->filesystem->emptyDirectory($targetDir, true);
        $git = new GitProcess($io, $targetDir);

        $timeout = ProcessExecutor::getTimeout();
        ProcessExecutor::setTimeout(0);
        [, , $outputs] = $git->exec('clone --depth 1 ' . self::GIT_URL . ' .');
        ProcessExecutor::setTimeout($timeout);

        if (!$this->alreadyInstalled()) {
            $io->errorLine('Failed cloning VIP GO MU plugins repository.');
            $io->lines(Io::ERROR, ...$outputs);
        }

        $this->copySunrise($io);
    }

    /**
     * @return bool
     */
    private function alreadyInstalled(): bool
    {
        $targetDir = $this->vipDirectories->vipMuPluginsDir();

        $installed = is_dir($targetDir)
            && file_exists("{$targetDir}/z-client-mu-plugins.php")
            && file_exists("{$targetDir}/lib/sunrise/sunrise.php")
            && file_exists("{$targetDir}/000-pre-vip-config/requires.php")
            && file_exists("{$targetDir}/wpcom-vip-two-factor/set-providers.php");

        return $installed;
    }

    /**
     * @param Io $io
     * @return void
     */
    private function copySunrise(Io $io): void
    {
        $sourceDir = $this->vipDirectories->vipMuPluginsDir();
        $sourcePath = "{$sourceDir}/lib/sunrise/sunrise.php";

        $wpDir = $this->config->wpConfig()[Config::WP_LOCAL_DIR_KEY];
        $targetDir = $this->config->basePath() . "/{$wpDir}/wp-content/";

        $this->filesystem->ensureDirectoryExists($targetDir);

        $basename = basename($sourcePath);
        $targetPath = "{$targetDir}/{$basename}";

        $io->verboseInfoLine("Copying '{$basename}' from '{$sourceDir}' to '{$targetDir}'.");

        if (file_exists($targetPath)) {
            $io->verboseCommentLine("'{$targetPath}' exists, replacing...");
            $this->filesystem->unlink($targetPath);
        }

        $this->filesystem->copy($sourcePath, $targetPath);
    }
}
