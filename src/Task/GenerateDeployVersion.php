<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Git\GitProcess;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateDeployVersion implements Task
{
    /**
     * @param VipDirectories $directories
     */
    public function __construct(private VipDirectories $directories)
    {
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'Generate deploy version files';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal()
            || $taskConfig->isGit()
            || $taskConfig->syncDevPaths()
            || $taskConfig->isVipDevEnv();
    }

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void
    {
        $targetDir = $this->directories->privateDir();
        $this->writeDeployId($io, $targetDir);
        if ($taskConfig->isLocal() || $taskConfig->isGit()) {
            $this->writeDeployTag($io, $targetDir);
        }

        $io->infoLine('Done.');
    }

    /**
     * @param Io $io
     * @param string $targetDir
     * @return void
     */
    private function writeDeployId(Io $io, string $targetDir): void
    {
        $deployId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            time() % 0xffff,
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );

        if (file_put_contents("{$targetDir}/deploy-id", $deployId) === false) {
            $io->errorLine("Failed writing deploy ID: '{$deployId}' to file.");
            return;
        }

        $io->infoLine("Deploy ID: '{$deployId}' written to file.");
    }

    /**
     * @param Io $io
     * @param string $targetDir
     * @return void
     */
    private function writeDeployTag(Io $io, string $targetDir): void
    {
        $git = new GitProcess($io);
        /**
         * @var bool $success
         * @var string $output
         */
        [$success, $output] = $git->execSilently('describe --abbrev=0 --exact-match');

        if (!$success && (stripos($output, 'no names') !== false)) {
            $io->infoLine('Deploy Git tag: No tag matches commit being deployed.');

            return;
        }

        $tag = trim($output);

        if (!$tag || !preg_match('~^v?[0-9]+~', $tag)) {
            return;
        }

        if (file_put_contents("{$targetDir}/deploy-ver", $tag) === false) {
            $io->errorLine("Failed writing deploy Git tag: '{$tag}' to file.");

            return;
        }

        $io->infoLine("Deploy Git tag: '{$tag}' written to file.");
    }
}
