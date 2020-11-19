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

use Inpsyde\VipComposer\Git\GitProcess;
use Inpsyde\VipComposer\Io;
use Inpsyde\VipComposer\VipDirectories;

final class GenerateDeployVersion implements Task
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
        return 'Generate deploy version files';
    }

    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool
    {
        return $taskConfig->isLocal() || $taskConfig->isGit() || $taskConfig->syncDevPaths();
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
     * @return bool
     * @throws \Exception
     */
    private function writeDeployId(Io $io, string $targetDir): bool
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

        if (!file_put_contents("{$targetDir}/deploy-id", $deployId)) {
            $io->errorLine("Failed writing deploy ID: '{$deployId}' to file.");

            return false;
        }

        $io->infoLine("Deploy ID: '{$deployId}' written to file.");

        return true;
    }

    /**
     * @param Io $io
     * @param string $targetDir
     * @return bool
     */
    private function writeDeployTag(Io $io, string $targetDir): bool
    {
        $git = new GitProcess($io);
        /**
         * @var bool $success
         * @var string $output
         */
        [$success, $output] = $git->execSilently('describe --abbrev=0 --exact-match');

        if (!$success && (stripos($output, 'no names') !== false)) {
            $io->infoLine('Deploy Git tag: No tag matches commit being deployed.');

            return false;
        }

        $tag = trim($output);

        if (!$tag || !preg_match('~^v?[0-9]+~', $tag)) {
            return false;
        }

        if (!file_put_contents("{$targetDir}/deploy-ver", $tag)) {
            $io->errorLine("Failed writing deploy Git tag: '{$tag}' to file.");

            return false;
        }

        $io->infoLine("Deploy Git tag: '{$tag}' written to file.");

        return true;
    }
}
