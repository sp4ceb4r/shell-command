<?php

use Shell\Output\ProcessOutputInterface;


/**
 * Class ReadCounter
 */
class ReadCounter implements  ProcessOutputInterface
{
    public $reads = 0;

    public $stdout = '';

    public function handle($stdout, $stderr)
    {
        $this->reads++;
        $this->stdout .= $stdout;
    }
}
