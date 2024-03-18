<?php

declare(strict_types=1);

namespace Inpsyde\VipComposer\Task;

use Inpsyde\VipComposer\Io;

interface Task
{
    /**
     * @param TaskConfig $taskConfig
     * @return bool
     */
    public function enabled(TaskConfig $taskConfig): bool;

    /**
     * @param Io $io
     * @param TaskConfig $taskConfig
     * @return void
     */
    public function run(Io $io, TaskConfig $taskConfig): void;

    /**
     * @return string
     */
    public function name(): string;
}
