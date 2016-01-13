<?php

namespace Shell\Output;


/**
 * Class OutputHandler
 */
class OutputHandler implements ProcessOutputInterface
{
    /**
     * Raw read stdout.
     *
     * @var string
     */
    protected $stdout;

    /**
     * Raw read stderr.
     *
     * @var string
     */
    protected $stderr;

    /**
     * Handle the command output.
     *
     * @param string $stdout
     * @param string $stderr
     * @return void
     */
    public function handle($stdout, $stderr)
    {
        $this->stderr .= $stderr;
        $this->stdout .= $stdout;
    }

    /**
     * The stdout read.
     *
     * @return string
     */
    public function readStdOut()
    {
        return $this->stdout;
    }

    /**
     * The stdout read split on newlines.
     * @return array
     */
    public function readStdOutLines()
    {
        return array_filter(array_map(function ($line) {
            return trim($line);
        }, explode("\n", $this->stdout)));
    }

    /**
     * The stderr read.
     *
     * @return string
     */
    public function readStdErr()
    {
        return $this->stderr;
    }

    /**
     * The stderr read split on newlines.
     *
     * @return string
     */
    public function readStdErrLines()
    {
        return array_filter(array_map(function ($line) {
            return trim($line);
        }, explode("\n", $this->stderr)));
    }
}