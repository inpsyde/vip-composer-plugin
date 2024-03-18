<?php

/**
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
    private string $workingDir;

    private ProcessExecutor $executor;

    private string $origWorkingDir;

    /** @var callable */
    private $outputCapture;

    /** @var list{string, string} */
    private array $captured = ['', ''];

    private bool $silent = false;

    /**
     * @param Io $io
     * @param string|null $workingDir
     * @param ProcessExecutor|null $executor
     */
    public function __construct(
        private Io $io,
        string $workingDir = null,
        ProcessExecutor $executor = null
    ) {

        $cwd = $workingDir ?? getcwd();
        if ($cwd === false) {
            throw new \Exception('Could not determine current dir');
        }

        $this->workingDir = $cwd;
        $this->origWorkingDir = $this->workingDir;
        $this->executor = $executor ?: new ProcessExecutor($io->composerIo());
        $this->outputCapture = function (string $type = '', string $buffer = ''): void {
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
     * @param array<string> $commands
     * @return array{bool, string, array<string>}
     */
    public function exec(string ...$commands): array
    {
        if (!$this->workingDir || !is_dir($this->workingDir)) {
            throw new \RuntimeException('Invalid working dir for Git operation.');
        }

        /** @var array<string> $outputs */
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

            /**
             * @var string $type
             * @var string $lastOutput
             */
            [$type, $lastOutput] = $this->captured;
            $this->captured = ['', ''];
            $outputs[] = $lastOutput;
            if ($exitCode !== 0 && $type === Process::ERR) {
                $this->silent or $this->io->errorLine($lastOutput);
            }
        }

        return [$exitCode === 0, $lastOutput, $outputs];
    }

    /**
     * @param string[] $commands
     * @return array
     */
    public function execSilently(string ...$commands): array
    {
        $this->silent = true;

        try {
            return $this->exec(...$commands);
        } finally {
            $this->silent = false;
        }
    }
}
