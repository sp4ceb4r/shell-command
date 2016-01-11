<?php

namespace Shell\Executors;

use Shell\Output\OutputHandler;
use Shell\Process;


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
