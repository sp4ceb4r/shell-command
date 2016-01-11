<?php

namespace Process;

use LogicException;


/**
 * Class ProcessBatch
 */
class ProcessBatch extends ProcessManager
{
    const MAX_CONCURRENT = 25;

    /**
     * Run each managed process with up to $batchSize in parallel.
     * This is a blocking call.
     *
     * @param int $batchSize
     * @return void
     * @throws LogicException
     */
    public function start($batchSize = 10)
    {
        if ($batchSize > static::MAX_CONCURRENT) {
            throw new LogicException('Maximum batch size is '.static::MAX_CONCURRENT.'.');
        }

        $finished = [];
        $running = [];
        $active = 0;

        while (!empty($this->managed)) {
            while ($active < static::MAX_CONCURRENT && !empty($this->managed)) {
                $p = array_shift($this->managed);
                array_push($running, $p->run(false));

                $active++;
            }

            foreach ($running as $index => $p) {
                if (!$p->isAlive()) {
                    $active--;
                    array_push($finished, $p);
                    unset($running[$index]);

                    if (!empty($this->managed)) {
                        array_push($running, array_shift($this->managed)->run());
                        $active++;
                    }
                }
            }

            reset($running);
        }

        unset($this->managed);
        $this->managed = $finished;
        unset($finished, $running);
    }
}