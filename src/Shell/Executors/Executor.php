<?php

namespace Shell\Executors;

use Shell\Output\ProcessOutputInterface;
use Shell\Process;


/**
 * Class Executor
 */
class Executor implements ExecutorInterface
{
    /**
     * @var Process[]
     */
    protected $pending;

    public function __construct()
    {
    }

    public function manage(Process ...$processes)
    {
        foreach ($processes as $process) {
            array_push($this->pending, $process);
        }
    }

    public function start()
    {
        $this->join();
    }

    public function join()
    {
        while (!empty($this->pending)) {
            $process = array_pop($pending);

            $process->run();
        }
    }

    public function hasAlive()
    {
        return false;
    }
}
