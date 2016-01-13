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
    protected $stdout = '';

    /**
     * Raw read stderr.
     *
     * @var string
     */
    protected $stderr = '';

    /**
     * Handle the command output.
     *
     * @param string $stdout
     * @param string $stderr
     * @return void
     */
    public function handle($stdout, $stderr)
    {
        if (!is_null($stdout)) {
            $this->stdout .= $stdout;
        }

        if (!is_null($stderr)) {
            $this->stderr .= $stderr;
        }
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
     *
     * @return array
     */
    public function readStdOutLines()
    {
        return $this->split($this->stdout);
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
        return $this->split($this->stderr);
    }

    /**
     * Splits text on newlines filtering out empty lines.
     *
     * @param $text
     * @param bool $keepEmpty
     * @return array
     */
    protected final function split($text, $keepEmpty = false)
    {
        $lines = array_map(function ($line) {
            return trim($line);
        }, explode("\n", $text));

        if (!$keepEmpty) {
            $lines = array_filter($lines);
            return array_values($lines);
        }

        return $lines;
    }
}
