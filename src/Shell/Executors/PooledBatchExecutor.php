<?php

namespace Shell\Executors;

use Shell\Output\OutputHandler;


/**
 * Class PooledBatchExecutor
 */
class PooledBatchExecutor extends PooledExecutor
{
    /**
     * @var int
     */
    protected $batchSize;

    /**
     * PooledBatchExecutor constructor.
     *
     * @param int $batchSize
     * @param int $poolSize
     * @param OutputHandler|null $handler
     */
    public function __construct($batchSize = 5, $poolSize = -1, OutputHandler $handler = null)
    {
        parent::__construct($poolSize, $handler);

        $this->batchSize = $batchSize;
    }

    /**
     * Run each managed process with up to $batchSize in parallel.
     *
     * @param OutputHandler $handler
     * @return void
     */
    public function start(OutputHandler $handler = null)
    {
        $handler = $handler ?: $this->handler;
        $finished = [];
        $running = [];
        $active = 0;

        while (!empty($this->processes)) {
            while ($active < $this->batchSize && !empty($this->processes)) {
                $p = array_shift($this->processes);
                array_push($running, $p->runAsync($handler));

                $active++;
            }

            foreach ($running as $index => $p) {
                if (!$p->isAlive()) {
                    $active--;
                    array_push($finished, $p);
                    unset($running[$index]);

                    if (!empty($this->processes)) {
                        array_push($running, array_shift($this->processes)->runAsync($handler));
                        $active++;
                    }
                }
            }

            reset($running);
        }

        unset($this->processes);
        $this->processes = $finished;
        unset($finished, $running);
    }
}
