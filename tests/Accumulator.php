<?php

use Shell\Output\ProcessOutputInterface;


/**
 * Class Accumulator
 */
class Accumulator implements ProcessOutputInterface
{
    /**
     * @var array
     */
    public $stdout = [];

    /**
     * @var array
     */
    public $stderr = [];

    /**
     * @param $stdout
     * @param $stderr
     */
    public function handle($stdout = null, $stderr = null)
    {
        if (!is_null($stdout)) {
            $this->stdout += array_filter(array_map('trim', preg_split('/\R/', $stdout)), function ($l) {
                return ($l !== '');
            });
        }

        if (!is_null($stderr)) {
            $this->stderr += array_filter(array_map('trim', preg_split('/\R/', $stderr)), function ($l) {
                return ($l !== '');
            });
        }
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
