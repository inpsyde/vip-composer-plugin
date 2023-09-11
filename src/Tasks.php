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

namespace Inpsyde\VipComposer;

use Composer\Util\Filesystem;
use Inpsyde\VipComposer\Task\Task;
use Inpsyde\VipComposer\Task\TaskConfig;

/*
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.NoAccessors
 */
class Tasks
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
     * @var TaskConfig
     */
    private $taskConfig;

    /**
     * @var Io
     */
    private $io;

    /**
     * @var \SplQueue
     */
    private $tasks;

    /**
     * @param Config $config
     * @param TaskConfig $taskConfig
     * @param VipDirectories $directories
     * @param Io $io
     * @param Filesystem $filesystem
     */
    public function __construct(
        Config $config,
        TaskConfig $taskConfig,
        VipDirectories $directories,
        Io $io,
        Filesystem $filesystem
    ) {

        $this->config = $config;
        $this->directories = $directories;
        $this->taskConfig = $taskConfig;
        $this->io = $io;
        $this->tasks = new \SplQueue();
    }

    /**
     * @param Task $task
     * @return Tasks
     */
    public function addTask(Task $task): Tasks
    {
        if ($task->enabled($this->taskConfig)) {
            $this->tasks->enqueue($task);
        }

        return $this;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        if (!$this->beforeRun()) {
            return;
        }

        $this->tasks->rewind();
        $this->io->composerIo()->write('');
        while ($this->tasks->count()) {
            /** @var Task $task */
            $task = $this->tasks->dequeue();
            $this->io->line('Task: ' . $task->name());
            $task->run($this->io, $this->taskConfig);
        }

        $this->afterRun();
    }

    /**
     * @return bool
     */
    private function beforeRun(): bool
    {
        if (!file_exists($this->config->composerLockPath())) {
            $this->io->error('Composer lock file not found.');
            $this->io->errorLine('Please install or update via Composer first.');

            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    private function afterRun(): void
    {
    }
}
