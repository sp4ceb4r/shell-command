<?php

namespace Shell\Executors;

use Shell\Output\ProcessOutputInterface;
use Shell\Process;


/**
 * Interface ExecutorInterface
 */
interface ExecutorInterface
{
    public function manage(Process ...$process);

    public function join();

    public function start();

    public function hasAlive();
}
