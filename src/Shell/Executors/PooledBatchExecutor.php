<?php

namespace Shell\Executors;

use Shell\Output\ProcessOutputInterface;


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
     */
    public function __construct($batchSize = 5, $poolSize = -1)
    {
        parent::__construct($poolSize);
        $this->batchSize = $batchSize;
    }

    /**
     * Run each managed process with up to $batchSize in parallel.
     *
     * @return void
     */
    public function start()
    {
        $finished = [];
        $running = [];
        $active = 0;

        while (!empty($this->processes)) {
            while ($active < $this->batchSize && !empty($this->processes)) {
                $p = array_shift($this->processes);
                array_push($running, $p->runAsync());

                $active++;
            }

            foreach ($running as $index => $p) {
                if (!$p->isAlive()) {
                    $active--;
                    array_push($finished, $p);
                    unset($running[$index]);

                    if (!empty($this->processes)) {
                        array_push($running, array_shift($this->processes)->runAsync());
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
