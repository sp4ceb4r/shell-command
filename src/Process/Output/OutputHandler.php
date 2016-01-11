<?php

namespace Process\Output;


/**
 * Interface OutputHandler
 */
interface OutputHandler
{
    public function handle($stdout, $stderr);
}