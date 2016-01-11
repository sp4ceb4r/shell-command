<?php

namespace Shell\Output;


/**
 * Interface OutputHandler
 */
interface OutputHandler
{
    public function handle($stdout, $stderr);
}
