<?php

namespace Process;

use Process\Exceptions\ProcessException;
use Process\Output\OutputHandler;


/**
 * Class ProcessManager
 */
class ProcessManager
{
    /**
     * @var Process[]
     */
    protected $managed;

    /**
     * ProcessManager constructor.
     *
     * @param Process[] $processes
     */
    public function __construct(Process ...$processes)
    {
        $this->managed = [];
        foreach ($processes as $process) {
            $this->managed[$process->getPid()] = $process;
        }

        $this->managed = func_get_args();
    }

    /**
     * Manage a selected process.
     *
     * @param Process $process
     * @return ProcessManager
     */
    public function manage(Process $process)
    {
        array_push($this->managed, $process);

        return $this;
    }

    /**
     * Run each managed process.
     * This is a non blocking call.
     *
     * @param OutputHandler $handler
     * @throws ProcessException
     */
    public function start(OutputHandler $handler = null)
    {
        foreach ($this->managed as $pid => $process) {
            if (!$process->isStarted()) {
                $process->run($handler);
            }
        }
    }

    /**
     * Block execution until each managed process has completed.
     *
     * @return void
     */
    public function join()
    {
        while (true) {
            $finished = true;
            foreach ($this->managed as $pid => $process) {
                if ($process->isAlive()) {
                    $finished = false;
                    break;
                }
            }

            if ($finished) {
                break;
            }

            unset($finished);
            usleep(50000);
        }
    }

    /**
     * Check if any managed processes are still running.
     *
     * @return bool
     */
    public function hasAlive()
    {
        foreach ($this->managed as $pid => $process) {
            if ($process->isAlive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a list of manage process ids.
     *
     * @return array
     */
    public function processes()
    {
        return array_keys($this->managed);
    }

    /**
     * Get a Process instance.
     *
     * @param $pid
     * @return Process|null
     */
    public function get($pid)
    {
        if (array_key_exists($pid, $this->managed)) {
            return $this->managed[$pid];
        }

        return null;
    }

    /**
     * Abandon the currently managed processes.
     *
     * @param bool $kill Kill the process if not completed.
     * @param string[] $pids Process ids to abandon
     */
    public function abandon($kill = true, ...$pids)
    {
        if (empty($pids)) {
            $pids = array_keys($this->managed);
        }

        foreach ($this->managed as $pid => $process) {
            if (in_array($pid, $pids)) {
                if ($kill) {
                    $process->kill();
                }

                unset($this->managed[$pid]);
            }
        }
    }

    /**
     * Check if a process has completed.
     *
     * @param Process $process
     * @return bool
     */
    protected function isComplete(Process $process)
    {
        return (!$process->isAlive() && ($process->getExitCode() > -1 || $process->getSignal() > -1));
    }
}
