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

use Inpsyde\VipComposer\Task\Task;
use Inpsyde\VipComposer\Task\TaskConfig;

class Tasks
{
    private \SplQueue $tasks;

    /**
     * @param Config $config
     * @param TaskConfig $taskConfig
     * @param Io $io
     */
    public function __construct(
        private Config $config,
        private TaskConfig $taskConfig,
        private Io $io
    ) {

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
