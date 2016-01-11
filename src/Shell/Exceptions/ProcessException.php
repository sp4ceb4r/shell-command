<?php

namespace Shell\Exceptions;

use Exception;
use Shell\Process;


/**
 * Class ProcessException
 */
class ProcessException extends Exception
{
    /**
     * @var Process
     */
    protected $process;

    /**
     * ProcessException constructor.
     *
     * @param string $message
     * @param Process $process
     * @param Exception|null $previous
     */
    public function __construct($message, Process $process, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->process = $process;
    }

    /**
     * Process getter.
     *
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }
}
