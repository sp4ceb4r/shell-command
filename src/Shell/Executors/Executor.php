<?php

namespace Shell\Executors;

use Shell\Output\OutputHandler;
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

    public function __construct(OutputHandler $handler) {
        $this->handler = $handler;
    }

    /**
     * @param OutputHandler
     */
    protected $handler;

    public function manage(Process ...$processes)
    {
        foreach ($processes as $process) {
            array_push($this->pending, $process);
        }
    }

    public function start(OutputHandler $handler = null)
    {
        $this->handler = $handler ?: $this->handler;
        $this->join();
    }

    public function join()
    {
        while (!empty($this->pending)) {
            $process = array_pop($pending);

            $process->run($this->handler);
        }
    }

    public function hasAlive()
    {
        return false;
    }
}
