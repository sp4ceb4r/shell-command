<?php

namespace Shell;

use Closure;
use Exception;
use InvalidArgumentException;
use LogicException;
use Shell\Commands\CommandInterface;
use Shell\Exceptions\ProcessException;
use Shell\Output\EchoOutputHandler;
use Shell\Output\ProcessOutputInterface;


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
     * The actual process id.
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
     * @param ProcessOutputInterface $outputHandler
     * @return Process
     */
    public static function make(CommandInterface $command, ProcessOutputInterface $outputHandler = null)
    {
        return new Process($command, null, $outputHandler);
    }

    /**
     * Process constructor.
     *
     * @param CommandInterface $command
     * @param null $cwd
     * @param ProcessOutputInterface $outputHandler
     */
    public function __construct(CommandInterface $command, $cwd = null, ProcessOutputInterface $outputHandler = null)
    {
        if (is_null($outputHandler)) {
            $outputHandler = new EchoOutputHandler();
        }

        $this->command = $command;
        $this->outputHandler = $outputHandler;

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
     * @return ProcessOutputInterface
     */
    public function getOutputHandler()
    {
        return $this->outputHandler;
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
            throw new InvalidArgumentException("Directory [$cwd] not found.");
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
     * @return Process
     * @throws ProcessException
     */
    public function runAsync()
    {
        if ($this->running) {
            throw new \RuntimeException('Process already running.');
        }

        try {
            $this->exec();
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException('Unknown process exception.', $this, $ex);
        }

        return $this;
    }

    /**
     * Execute the command synchronously.
     *
     * @param int $timeout
     * @return Process
     * @throws ProcessException
     */
    public function run($timeout = -1)
    {
        if ($this->running) {
            throw new \RuntimeException('Process already running.');
        }

        try {
            $this->exec();

            $this->wait($timeout);
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException('Unknown process exception.', $this, $ex);
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
        if ($this->running) {
            throw new \RuntimeException('Process already running.');
        }

        try {
            $this->exec();
        } catch (ProcessException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->cleanup();
            throw new ProcessException('Unknown process exception.', $this, $ex);
        }

        return $this;
    }

    /**
     * Wait for the process to finish execution.
     *
     * @param int $timeout
     */
    public function wait($timeout = -1)
    {
        $forever = ($timeout < 0);

        while ($this->isAlive()) {
            $this->read();

            if (!$forever && (microtime(true) - $this->start > $timeout)) {
                $this->kill();
                break;
            }

            usleep(5000);
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
            throw new LogicException('Process is not alive.');
        } elseif (!$this->interactive) {
            throw new LogicException("Process [{$this->pid}] not running interactively.");
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
        if (!$this->running) {
            throw new LogicException('Process not running.');
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
     * @return void
     */
    public function read()
    {
        $stdout = $this->readStream(static::STDOUT);
        $stderr = $this->readStream(static::STDERR);

        $this->outputHandler->handle($stdout, $stderr);
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
            throw new LogicException('Process already closed.', static::ERR_COMPLETED);
        } elseif ($this->running) {
            throw new LogicException('Process already running.', static::ERR_RUNNING);
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
     * @throws \RuntimeException
     */
    protected function cleanup()
    {
        if ($this->running) {
            throw new \RuntimeException('Cleanup error - process still running.');
        }

        if (!isset($this->resource)) {
            return;
        }

        $this->pid = -1;
        $this->read();

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
                call_user_func($this->onError, $this);
            } else {
                throw new ProcessException('Error executing process.', $this);
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

        $this->resource = proc_open($this->command->serialize(),
                                    static::$descriptorspec,
                                    $this->pipes,
                                    $this->cwd,
                                    null);

        if ($this->resource === false) {
            throw new ProcessException('Call to [proc_open] failed.', $this);
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
