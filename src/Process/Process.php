<?php

namespace Process;

use Closure;
use Exception;
use InvalidArgumentException;
use LogicException;
use Process\Commands\CommandInterface;
use Process\Exceptions\ProcessException;
use Process\Output\OutputHandler;
use RuntimeException;


/**
 * Class Process
 */
class Process
{
    const STDIN = 0;
    const STDOUT = 1;
    const STDERR = 2;

    const ERR_COMPLETED = 17;
    const ERR_RUNNING = 20;

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
     * Default pipe descriptors for proc_open.
     *
     * @var array
     */
    protected static $descriptorspec = [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w'],
    ];

    /**
     * The command to execute.
     *
     * @var CommandInterface
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
     * Start time in microseconds since the epoch.
     *
     * @var int
     */
    protected $start;

    /**
     * Working directory for the command execute in.
     *
     * @var string
     */
    protected $cwd;

    /**
     * Generated id for process.
     *
     * @var string
     */
    protected $id;

    /**
     * The actual process id.
     *
     * @var int
     */
    protected $pid;

    /**
     * @var
     */
    protected $shellId;

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
     * @var Closure
     */
    protected $onSuccess;

    /**
     * @var Closure
     */
    protected $onError;

    /**
     * @var OutputHandler
     */
    protected $outputHandler;

    /**
     * @var array
     */
    protected $pipes = [];


    /**
     * Static Process constructor (preferred when chaining calls).
     *
     * @param CommandInterface $command
     * @return Process
     */
    public static function make(CommandInterface $command)
    {
        return new Process($command);
    }

    /**
     * Process constructor.
     *
     * @param CommandInterface $command
     * @param null $cwd
     */
    public function __construct(CommandInterface $command, $cwd = null)
    {
        $this->id = substr(sha1(microtime()), -7);
        $this->command = $command;

        if (is_null($cwd)) {
            $cwd = __DIR__;
        }
        $this->cwd = $cwd;
    }

    /**
     * Process destructor.
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Get a string representation of Process.
     *
     * @return string
     */
    public function __toString()
    {
        return "Process [{$this->command}, {$this->id}]";
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
    public function onError(Closure $closure = null)
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
    public function onSuccess(Closure $closure = null)
    {
        $this->onSuccess = $closure;

        return $this;
    }

    /**
     * Execute the command asynchronously.
     *
     * @param OutputHandler $handler
     * @return Process
     * @throws ProcessException
     */
    public function runAsync(OutputHandler $handler = null)
    {
        if ($this->running) {
            throw new LogicException("$this already running.");
        }

        $this->outputHandler = $handler;

        try {
            $this->exec();
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException("Error running $this.", $this, $ex);
        }

        return $this;
    }

    /**
     * Execute the command synchronously.
     *
     * @param OutputHandler $handler
     * @param int $timeout
     * @return Process
     * @throws ProcessException
     */
    public function run(OutputHandler $handler = null, $timeout = -1)
    {
        if ($this->running) {
            throw new LogicException("$this already running.");
        }

        $this->outputHandler = $handler;

        try {
            $this->exec();

            $this->wait($timeout);
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException("Error running $this.", $this, $ex);
        }

        return $this;
    }

    /**
     * Execute the command interactively.
     *
     * @param OutputHandler $handler
     * @return Process
     * @throws ProcessException
     */
    public function runInteractive(OutputHandler $handler = null)
    {
        if ($this->running) {
            throw new LogicException("$this already running.");
        }

        $this->outputHandler = $handler;

        try {
            $this->exec();
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException("Error running $this.", $this, $ex);
        }

        return $this;
    }

    /**
     * Wait for the process to finish execution.
     *
     * @param int $timeout
     * @param OutputHandler $handler
     */
    public function wait($timeout = -1, OutputHandler $handler = null)
    {
        $handler = $handler ?: $this->outputHandler;

        $forever = ($timeout < 0);

        while ($this->isAlive()) {
            usleep(5000);

            $this->read($handler);

            if (!$forever && (microtime(true) - $this->start > $timeout)) {
                $this->kill();
                break;
            }
        }

        $this->cleanup();
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
        if (!$this->isAlive()) {
            throw new LogicException("Process is not alive.");
        } elseif (!$this->interactive) {
            throw new LogicException("Process [{$this->pid}] not run interactively.");
        }

        $bytes = fwrite($this->pipes[static::STDIN], $input, strlen($input));
        if ($bytes === false) {
            $this->kill();
            throw new ProcessException("Error sending [$input] to $this stdin.", $this);
        }
    }

    /**
     * Get Process id.
     *
     * @return int
     */
    public function getPid()
    {
        return $this->id;
    }

    public function getSystemPid()
    {
        if (!$this->running) {
            throw new LogicException("Process not running.");
        }

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
        if ($this->signaled) {
            return $this->termsig;
        } elseif ($this->stopped) {
            return $this->stopsig;
        } else {
            return -1;
        }
    }

    /**
     * Get the process command.
     *
     * @return CommandInterface
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Has the process been started.
     * @return bool
     */
    public function isStarted()
    {
        return isset($this->start);
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

        $this->cleanup();
        return true;
    }

    /**
     * @param OutputHandler $handler
     * @return mixed
     */
    public function read(OutputHandler $handler = null)
    {
        $stdout = $this->readStream(static::STDOUT);
        $stderr = $this->readStream(static::STDERR);

        if (!is_null($handler)) {
            $handler->handle($stdout, $stderr);
        }
    }

    /**
     * Reads the current contents of the specified pipe.
     *
     * @param $id
     * @return string
     */
    protected function readStream($id)
    {
        $data = stream_get_contents($this->pipes[$id]);

        return $data;
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
     * proc_get_status returns the process id of the subshell opened
     * when proc_open is called, and not the actual process id of
     * the command executed. In order to the the actual command process
     * id we must call "command" with "exec". The downside to this
     * is that we are unable to execute compound commands.
     *
     * TODO: Verify that the actual command process id will be subshell pid+1
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
     * @throws RuntimeException
     */
    protected function cleanup()
    {
        if ($this->running) {
            throw new RuntimeException("Process [{$this->pid}] still running.");
        }

        if (!isset($this->resource)) {
            return;
        }

        $this->pid = -1;
        $this->read($this->outputHandler);

        foreach ($this->pipes as $index => $pipe) {
            if (!$this->interactive && $index === 0) {
                continue;
            }

            fclose($pipe);
        }

        proc_close($this->resource);
        unset($this->resource, $this->pipes);

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
     * @param bool $interactive
     * @throws LogicException
     * @throws ProcessException
     */
    private function exec($interactive = false)
    {
        $this->validate();

        $this->resource = proc_open($this->wrapCommand($this->command->serialize()),
                                    static::$descriptorspec,
                                    $this->pipes,
                                    $this->cwd,
                                    null);

        if ($this->resource === false) {
            throw new ProcessException("proc_open failed.", $this);
        }

        $this->start = microtime(true);

        $this->running = true;

        if (!$interactive) {
            fclose($this->pipes[0]);
        }

        foreach ($this->pipes as $index => $pipe) {
            if ($index === 0) {
                continue;
            }

            stream_set_blocking($pipe, static::NON_BLOCKING);
        }
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
