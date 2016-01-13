<?php

namespace Shell\Commands;

use Shell\Exceptions\CommandException;


/**
 * Class Command
 */
class Command implements CommandInterface
{
    protected $binary;

    protected $args = [];

    protected $options = [];

    protected $compounds = [];

    /**
     * Command static constructor (preferred when using builders).
     *
     * @param $name
     * @return Command
     */
    public static function make($name)
    {
        return new Command($name);
    }

    /**
     * Command constructor.
     *
     * @param $name
     * @param array $args
     * @param array $options
     */
    public function __construct($name, array $args = [], array $options = [])
    {
        if (preg_match("/(?:exec )?(?<cmd>[^\\s]+).*/", $name, $matches) === 1) {
            $this->binary = $this->find($matches['cmd']);
        } else {
            throw new CommandException("Command [$name] not recognized.");
        }

        $this->args = $args;
        $this->options = $options;
        $this->compounds[] = $this;
    }

    /**
     * Set the command arguments.
     *
     * @param array $args
     * @return $this
     */
    public function withArgs($args = [])
    {
        if (!is_array($args)) {
            $args = func_get_args();
        }

        $this->args = $args;
        return $this;
    }

    /**
     * Set the command options.
     *
     * @param array $options
     * @return Command
     */
    public function withOptions(array $options = [])
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Format the command.
     *
     * @return string
     */
    public function serialize()
    {
        $args = join(' ', $this->args);

        // nargs must be last and only 1

        $options = [];
        $narg = null;
        foreach ($this->options as $option => $value) {
            if (is_array($value)) {
                $narg = "$option ".join(' ', array_map('strval', $value));
                continue;
            }

            if (is_numeric($option)) {
                $options[] = strval($value);
            } elseif (is_bool($value)) {
                $options[] = $option;
            } else {
                $options[] = trim("$option $value");
            }
        }

        if (!is_null($narg)) {
            array_push($options, $narg);
        }

        return trim(preg_replace('/[\s]{2,}/', ' ', "{$this->binary} $args ".join(' ', $options)) ?: '');
    }

    /**
     * Get the string representation of the command.
     *
     * @return string
     */
    public function __toString()
    {
        return "Command [{$this->binary}]";
    }

    /**
     * Verify the command binary.
     *
     * @return void
     * @throws CommandException
     */
    public function validate()
    {
        if ($this->binary === false) {
            throw new CommandException("Command not found.");
        }

        if (!is_executable($this->binary)) {
            throw new CommandException("$this not executable.");
        }

        $nargs = false;
        foreach ($this->options as $option => $value) {
            if (is_array($value)) {
                if ($nargs) {
                    throw new CommandException("Multiple nargs detected.");
                }

                $nargs = true;
            }
        }
    }

    /**
     * Find the path to the commands binary.
     *
     * @param $command
     * @return bool|string
     */
    private function find($command)
    {
        if (is_file($command)) {
            return $command;
        }

        foreach (explode(':', $_SERVER['PATH']) as $path) {
            $tmp = "$path/$command";
            if (is_file($tmp)) {
                return $tmp;
            }

            unset($tmp);
        }

        return false;
    }
}
