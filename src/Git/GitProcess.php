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

namespace Inpsyde\VipComposer\Git;

use Composer\Util\ProcessExecutor;
use Inpsyde\VipComposer\Io;
use Symfony\Component\Process\Process;

class GitProcess
{

    /**
     * @var string
     */
    private $workingDir;

    /**
     * @var string
     */
    private $origWorkingDir;

    /**
     * @var Io
     */
    private $io;

    /**
     * @var ProcessExecutor
     */
    private $executor;

    /**
     * @var callable
     */
    private $outputCapture;

    /**
     * @var array
     */
    private $captured = ['', ''];

    /**
     * @param Io $io
     * @param string $workingDir
     * @param ProcessExecutor|null $executor
     */
    public function __construct(
        Io $io,
        string $workingDir = null,
        ProcessExecutor $executor = null
    ) {

        $this->workingDir = $workingDir ?: getcwd();
        $this->origWorkingDir = $this->workingDir;
        $this->io = $io;
        $this->executor = $executor ?: new ProcessExecutor($io->composerIo());
        $this->outputCapture = function (string $type = '', string $buffer = '') {
            $this->captured = [$type, $buffer];
        };
    }

    /**
     * @param string $workingDir
     * @return GitProcess
     */
    public function cd(string $workingDir): GitProcess
    {
        if (!$workingDir || !is_dir($workingDir)) {
            throw new \RuntimeException('Invalid working dir for Git operation.');
        }

        $this->workingDir = $workingDir;

        return $this;
    }

    /**
     * @return GitProcess
     */
    public function resetCwd(): GitProcess
    {
        $this->workingDir = $this->origWorkingDir;

        return $this;
    }

    /**
     * @param string[] $commands
     * @return array
     */
    public function exec(string ...$commands): array
    {
        if (!$this->workingDir || !is_dir($this->workingDir)) {
            throw new \RuntimeException('Invalid working dir for Git operation.');
        }

        $outputs = [];
        $lastOutput = '';
        $exitCode = 0;

        while ($exitCode === 0 && $commands) {
            $command = array_shift($commands);
            $this->io->verboseCommentLine("Executing `git {$command}`");

            $exitCode = $this->executor->execute(
                "git {$command}",
                $this->outputCapture,
                $this->workingDir
            );

            [$type, $lastOutput] = $this->captured;
            $this->captured = ['', ''];
            $outputs[] = $lastOutput;
            if ($exitCode !== 0 && $type === Process::ERR) {
                $this->io->errorLine($lastOutput);
            }
        }

        return [$exitCode === 0, $lastOutput, $outputs];
    }
}
