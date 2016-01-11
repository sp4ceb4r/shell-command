<?php

namespace Process;

use Closure;
use Exception;
use InvalidArgumentException;
use LogicException;


/**
 * Class Process
 */
class Process
{
    const ERR_COMPLETED = 17;
    const ERR_RUNNING = 20;

    /**
     * Default pipe descriptors for proc_open.
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
     * The command to execute.
     *
     * @var Command
     */
    protected $command;

    /**
     * The process resource.
     *
     * @var resource
     */
    protected $resource;

    /**
     * Run the process interactively.
     *
     * @var bool
     */
    protected $interactive = false;

    /**
     * Working directory for the command execute in.
     *
     * @var string
     */
    protected $cwd;

    /**
     * Descriptors for proc_open pipes.
     *
     * @var array
     */
    protected $descriptors;

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
     * The process exit code.
     *
     * @var int
     */
    protected $exitcode = -1;

    /**
     * Was the process terminated by uncaught signal.
     *
     * @var bool
     */
    protected $signaled = false;

    /**
     * Signal which caused the process to stop. Only meaningful when $signaled true.
     *
     * @var int
     */
    protected $stopsig;

    /**
     * Was the process stopped by a signal.
     *
     * @var bool
     */
    protected $stopped = false;

    /**
     * Signal which terminated the process. Only meaningful when $stopped true.
     *
     * @var int
     */
    protected $termsig;

    /**
     * Was the process killed by the user.
     *
     * @var bool
     */
    protected $killed = false;

    /**
     * @var Closure
     */
    protected $onSuccess;

    /**
     * @var Closure
     */
    protected $onError;

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

        $this->descriptors = static::$descriptorspec;
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
            throw new InvalidArgumentException("Dir [$cwd] not found.");
        }

        $this->cwd = $cwd;
        return $this;
    }

    /**
     * Set the function to be called once the process completes
     * if the process exits non 0.
     *
     * @param Closure $closure
     * @return Process
     */
    public function onError(Closure $closure)
    {
        $this->onError = $closure;

        return $this;
    }

    /**
     * Set the function to be called once the process completes
     * if the process exits 0.
     *
     * @param Closure $closure
     * @return Process
     */
    public function onSuccess(Closure $closure)
    {
        $this->onSuccess = $closure;

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
            }
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException("Error executing command [{$this->command}].", $this, $ex);
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
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException("Error executing command [{$this->command}].", $this, $ex);
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
            $this->kill();
            throw new ProcessException("Error sending [$input] to process stdin.", $this);
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
        $this->cleanup();
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
    protected final function validate()
    {
        if (isset($this->pid) && !isset($this->resource)) {
            throw new LogicException("Process [{$this->pid}] closed.", static::ERR_COMPLETED);
        } elseif ($this->running) {
            throw new LogicException("Process [{$this->pid}] running.", static::ERR_RUNNING);
        }

        $this->command->validate();
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
     * Release the process memory.
     *
     * @return void
     */
    protected function cleanup()
    {
        if (!$this->running) {
            throw new LogicException("Process [{$this->pid}] still running.");
        }

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

        if ($this->exitcode === 0) {
            if (isset($this->onSuccess)) {
                call_user_func($this->onSuccess);
            }
        } else {
            if (isset($this->onError)) {
                call_user_func($this->onError);
            }
        }
    }

    /**
     * Execute the command.
     *
     * @param bool $blocking
     * @param bool $interactive
     * @throws LogicException
     * @throws ProcessException
     */
    private function exec($blocking = true, $interactive = false)
    {
        $this->validate();

        $mode = $blocking ? static::BLOCKING : static::NON_BLOCKING;

        $this->resource = proc_open($this->wrapCommand($this->command->serialize()),
            $this->descriptors,
            $pipes,
            $this->cwd,
            null);

        if ($this->resource === false) {
            $this->cleanup();
            throw new ProcessException("Error executing command [{$this->command}].", $this);
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
