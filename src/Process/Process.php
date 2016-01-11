<?php

namespace Process;

use Exception;
use LogicException;


/**
 * Class Process
 */
class Process
{
    const ERR_COMPLETED = 17;
    const ERR_RUNNING = 20;

    /**
     * Default descriptors for proc_open.
     * 0 - stdin
     * 1 - stdout
     * 2 - stderr
     *
     * @var array
     */
    protected static $descriptorspec = [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w'],
    ];

    /**
     * Stream mode - reads wait until data available.
     *
     * @var bool
     */
    const BLOCKING = true;

    /**
     * Stream mode - reads return immediately with whatever is available.
     *
     * @var bool
     */
    const NON_BLOCKING = false;

    /**
     * The process resource.
     *
     * @var resource
     */
    protected $resource;

    /**
     * The command to execute.
     *
     * @var Command
     */
    protected $command;

    /**
     * Working directory for the command execute in.
     *
     * @var string|null
     */
    protected $cwd;

    /**
     * Run the process interactively.
     *
     * @var bool
     */
    protected $interactive = false;

    /**
     * Descriptors for proc_open pipes.
     *
     * @var array
     */
    protected $pipedescriptors;

    /**
     * The Process Id.
     *
     * @var int
     */
    protected $pid;

    /**
     * Is the process alive.
     *
     * @var bool
     */
    protected $running = false;

    /**
     * Was the process terminated by uncaught signal.
     *
     * @var bool
     */
    protected $signaled = false;

    /**
     * Was the process stopped by a signal.
     *
     * @var bool
     */
    protected $stopped = false;

    /**
     * Was the process killed.
     *
     * @var bool
     */
    protected $killed = false;

    /**
     * The process exit code.
     *
     * @var int
     */
    protected $exitcode = -1;

    /**
     * Signal which caused the process to stop. Only meaningful when $signaled true.
     *
     * @var int
     */
    protected $stopsig;

    /**
     * Signal which terminated the process. Only meaningful when $stopped true.
     *
     * @var int
     */
    protected $termsig;

    /**
     * Process stdin stream.
     *
     * @var resource
     */
    protected $stdin;

    /**
     * Process stdout stream.
     *
     * @var resource|string
     */
    protected $stdout;

    /**
     * Process stderr stream.
     *
     * @var resource|string
     */
    protected $stderr;

    /**
     * Static Process constructor (preferred when chaining calls).
     *
     * @param Command $command
     * @return Process
     */
    public static function process(Command $command)
    {
        return new Process($command);
    }

    /**
     * Process constructor.
     *
     * @param Command $command
     * @param null $cwd
     */
    public function __construct(Command $command, $cwd = null)
    {
        $this->command = $command;
        if (is_null($cwd)) {
            $cwd = __DIR__;
        }

        $this->cwd = $cwd;

        $this->pipedescriptors = static::$descriptorspec;
    }

    /**
     * Process destructor.
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Set the working directory for the process.
     *
     * @param $cwd
     * @return Process
     * @throws LogicException
     */
    public function usingCwd($cwd)
    {
        if (!is_dir($cwd)) {
            throw new LogicException("Dir [$cwd] not found.");
        }

        $this->cwd = $cwd;
        return $this;
    }

    /**
     * Execute the command.
     *
     * @param bool $blocking
     * @return Process
     * @throws ProcessException
     */
    public function run($blocking = true)
    {
        try {
            $this->exec($blocking);

            if ($blocking) {
                while ($this->isAlive()) {
                    usleep(5000);
                }

                $this->cleanup();
            } else {
                if ($this->getExitCode() !== 0) {
                    if ($this->stopped) {
                        $code = $this->termsig;
                    } elseif ($this->signaled) {
                        $code = $this->stopsig;
                    } else {
                        $code = $this->getExitCode();
                    }

                    throw new ProcessException("Error executing [{$this->command}].", $code);
                }
            }
        } catch (Exception $ex) {
            throw new ProcessException("Error executing command [{$this->command}].", $ex->getCode(), $ex);
        }

        return $this;
    }

    /**
     * Execute the command interactively.
     *
     * @return Process
     * @throws ProcessException
     */
    public function runInteractive()
    {
        try {
            $this->exec();
        } catch (Exception $ex) {
            throw new ProcessException("Error executing command [{$this->command}].", $ex->getCode(), $ex);
        }

        return $this;
    }

    /**
     * Sends the text to the interactive processs via its stdin.
     *
     * @param $input
     * @throws LogicException
     * @throws ProcessException
     */
    public function send($input)
    {
        if (!$this->interactive) {
            throw new LogicException("Process [{$this->pid}] not run interactively.");
        } elseif (!$this->isAlive()) {
            throw new LogicException("Process [{$this->pid}] is not alive.");
        }

        $bytes = fwrite($this->stdin, $input, strlen($input));
        if ($bytes === false) {
            throw new ProcessException("Error sending [$input] to process stdin.");
        }
    }

    /**
     * Get the process exit code.
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitcode;
    }

    /**
     * Get the terminating signal.
     *
     * @return int
     */
    public function getSignal()
    {
        if ($this->stopped) {
            return $this->termsig;
        } elseif ($this->signaled) {
            return $this->stopsig;
        } else {
            return -1;
        }
    }

    /**
     * Get Process id.
     *
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Is the process alive.
     *
     * @return bool
     */
    public function isAlive()
    {
        if (!isset($this->resource)) {
            return false;
        }

        $this->checkStatus();

        return $this->running;
    }

    /**
     * Kill the running process.
     *
     * @param int $signal
     * @return bool
     */
    public function kill($signal = SIGTERM)
    {
        if (!$this->isAlive()) {
            return false;
        }

        if (!proc_terminate($this->resource, $signal)) {
            return false;
        }

        while ($this->isAlive()) {
            usleep(50000);
        }

        $this->killed = true;
        return true;
    }

    /**
     * Was the process killed.
     *
     * @return bool
     */
    public function killed()
    {
        return $this->killed;
    }

    /**
     * Validate the process can be run.
     *
     * @throws LogicException
     */
    protected function validate()
    {
        if (isset($this->pid) && !isset($this->resource)) {
            throw new LogicException("Process [{$this->pid}] closed.", static::ERR_COMPLETED);
        } elseif ($this->running) {
            throw new LogicException("Process [{$this->pid}] running.", static::ERR_RUNNING);
        }

        $this->command->validate();
    }

    /**
     * Execute the command.
     *
     * @param bool $blocking
     * @param bool $interactive
     * @throws LogicException
     * @throws ProcessException
     */
    protected function exec($blocking = true, $interactive = false)
    {
        $this->validate();

        $mode = $blocking ? static::BLOCKING : static::NON_BLOCKING;

        $this->resource = proc_open($this->wrapCommand($this->command->serialize()),
                                    $this->pipedescriptors,
                                    $pipes,
                                    $this->cwd,
                                    null);

        if ($this->resource === false) {
            throw new ProcessException("Error executing command [{$this->command}].");
        }

        $this->running = true;

        if ($interactive) {
            $this->stdin = $pipes[0];
        } else {
            fclose($pipes[0]);
        }

        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        stream_set_blocking($this->stdout, $mode);
        stream_set_blocking($this->stderr, $mode);

        unset($pipes);
    }

    /**
     * Release the process memory.
     *
     * @return void
     */
    protected function cleanup()
    {
        if (!isset($this->resource)) {
            return;
        }

        if (isset($this->stdout) && is_resource($this->stdout)) {
            $tmp = $this->stdout;
            unset($this->stdout);

            $this->stdout = stream_get_contents($tmp);
            fclose($tmp);
            unset($tmp);
        }

        if (isset($this->stderr) && is_resource($this->stderr)) {
            $tmp = $this->stderr;
            unset($this->stderr);

            $this->stderr = stream_get_contents($tmp);
            fclose($tmp);
            unset($tmp);
        }

        proc_close($this->resource);
        unset($this->resource);
    }

    /**
     * Read the latest output from stdout.
     *
     * @return string
     */
    public function readStdOut()
    {
        if (is_resource($this->stdout)) {
            return stream_get_contents($this->stdout);
        } elseif (is_null($this->stdout)) {
            return null;
        }

        $stdout = $this->stdout;
        $this->stdout = null;

        return $stdout;
    }

    /**
     * Read the latest output from stderr.
     *
     * @return string
     */
    public function readStdErr()
    {
        if (is_resource($this->stderr)) {
            return stream_get_contents($this->stderr);
        } elseif (is_null($this->stderr)) {
            return null;
        }

        $stderr = $this->stderr;
        $this->stderr = null;

        return $stderr;
    }

    /**
     * Wrap the command with exec so that the PID returned
     * by proc_get_status is the actual PID of the running command.
     *
     * @param string $cmd
     * @return string
     * @throws LogicException
     */
    protected function wrapCommand($cmd)
    {
        if (substr($cmd, 0, 4) !== 'exec') {
            return "exec $cmd";
        }

        return $cmd;
    }

    /**
     * Check the current processes status.
     */
    private function checkStatus()
    {
        if (!$this->running) {
            return;
        }

        if ($this->running) {
            foreach (proc_get_status($this->resource) as $key => $value) {
                $this->$key = $value;
            }
        }

        if (!$this->running) {
            $this->cleanup();
        }
    }
}
