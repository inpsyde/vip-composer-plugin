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

namespace Inpsyde\VipComposer;

use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
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
     * @var IOInterface
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
     * @param IOInterface $io
     * @param string $workingDir
     * @param ProcessExecutor|null $executor
     */
    public function __construct(
        IOInterface $io,
        string $workingDir = null,
        ProcessExecutor $executor = null
    ) {

        $this->workingDir = $workingDir ?: getcwd();
        $this->origWorkingDir = $this->workingDir;
        $this->io = $io;
        $this->executor = $executor ?: new ProcessExecutor($io);
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
        $vvv = $this->io->isVeryVerbose();

        while ($exitCode === 0 && $commands) {
            $command = array_shift($commands);
            $vvv and $this->io->write("     <comment>Executing </comment>`git {$command}`");

            $exitCode = $this->executor->execute(
                "git {$command}",
                $this->outputCapture,
                $this->workingDir
            );

            list($type, $lastOutput) = $this->captured;
            $this->captured = ['', ''];
            $outputs[] = $lastOutput;
            if ($exitCode !== 0 && $type === Process::ERR) {
                $this->io->writeError("<error>{$lastOutput}</error>");
            }
        }

        return [$exitCode === 0, $lastOutput, $outputs];
    }
}
