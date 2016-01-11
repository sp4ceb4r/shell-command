<?php

namespace Process\Executors;

use Process\Output\OutputHandler;
use Process\Process;


/**
 * Interface ExecutorInterface
 */
interface ExecutorInterface
{
    public function manage(Process ...$process);

    public function join();

    public function start(OutputHandler $handler = null);

    public function hasAlive();
}