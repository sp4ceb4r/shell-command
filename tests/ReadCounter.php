<?php

use Shell\Output\ProcessOutputInterface;


/**
 * Class ReadCounter
 */
class ReadCounter implements ProcessOutputInterface
{
    public $reads = 0;

    public $stdout = '';

    public function handle($stdout, $stderr)
    {
        $this->reads++;
        $this->stdout .= $stdout;
    }

    /**
     * The stdout read.
     *
     * @return string
     */
    public function readStdOut()
    {
        // TODO: Implement readStdOut() method.
    }

    /**
     * The stdout read split on newlines.
     *
     * @return array
     */
    public function readStdOutLines()
    {
        // TODO: Implement readStdOutLines() method.
    }

    /**
     * The stderr read.
     *
     * @return string
     */
    public function readStdErr()
    {
        // TODO: Implement readStdErr() method.
    }

    /**
     * The stderr read split on newlines.
     *
     * @return array
     */
    public function readStdErrLines()
    {
        // TODO: Implement readStdErrLines() method.
    }
}
