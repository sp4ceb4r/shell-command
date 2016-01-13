<?php

namespace Shell\Output;


/**
 * Class DefaultOutputHandler
 */
class DefaultOutputHandler implements ProcessOutputInterface
{
    protected $stdout;

    protected $stderr;

    /**
     * Handle the command output.
     *
     * @param $stdout
     * @param $stderr
     * @return void
     */
    public function handle($stdout, $stderr)
    {
        if (trim($stdout)) {
            echo "stdout: $stdout\n";
        }
        if (trim($stderr)) {
            echo "stderr: $stderr\n";
        }

        $this->stderr .= $stderr;
        $this->stdout .= $stdout;
    }

    /**
     * The stdout read.
     *
     * @return string
     */
    public function readStdout()
    {
        return $this->stdout;
    }

    /**
     * The stdout read split on newlines.
     * @return array
     */
    public function readStdoutLines()
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
    public function readStderr()
    {
        return $this->stderr;
    }

    /**
     * The stderr read split on newlines.
     *
     * @return string
     */
    public function readStderrLines()
    {
        return array_filter(array_map(function ($line) {
            return trim($line);
        }, explode("\n", $this->stderr)));
    }
}