<?php

namespace Shell\Output;


/**
 * Interface OutputHandler
 */
interface OutputHandler
{
    /**
     * Handle the command output.
     *
     * @param $stdout
     * @param $stderr
     * @return mixed
     */
    public function handle($stdout, $stderr);
}
