<?php

use Process\Output\OutputHandler;


/**
 * Class ReadCounter
 */
class ReadCounter implements  OutputHandler
{
    public $reads = 0;

    public $stdout = '';

    public function handle($stdout, $stderr)
    {
        $this->reads++;
        $this->stdout .= $stdout;
    }
}