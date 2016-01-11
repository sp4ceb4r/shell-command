<?php

use Shell\Output\OutputHandler;


/**
 * Class Accumulator
 */
class Accumulator implements OutputHandler
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
}
