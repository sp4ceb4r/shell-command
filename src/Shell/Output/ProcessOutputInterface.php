<?php

namespace Shell\Output;


/**
 * Interface ProcessOutputInterface
 */
interface ProcessOutputInterface
{
    /**
     * Handle the command output.
     *
     * @param string $stdout
     * @param string $stderr
     * @return void
     */
    public function handle($stdout, $stderr);

    /**
     * The stdout read.
     *
     * @return string
     */
    public function readStdOut();

    /**
     * The stdout read split on newlines.
     * @return array
     */
    public function readStdOutLines();

    /**
     * The stderr read.
     *
     * @return string
     */
    public function readStdErr();

    /**
     * The stderr read split on newlines.
     *
     * @return array
     */
    public function readStdErrLines();
}
