<?php

namespace Shell\Output;


/**
 * Class EchoOutputHandler
 */
class EchoOutputHandler extends OutputHandler
{
    /**
     * Handle the command output.
     *
     * @param $stdout
     * @param $stderr
     * @return void
     */
    public function handle($stdout, $stderr)
    {
        parent::handle($stdout, $stderr);

        if (trim($stdout)) {
            echo trim($stdout)."\n";
        }
        if (trim($stderr)) {
            echo trim($stderr)."\n";
        }
    }
}
