# shell-command

Wrapper for php proc_open

## Getting Started

```
$process = Process::make(Command::make('sleep')->withArgs(5))
    ->runAsync();
$process->wait(2);
```

### Installing

```
composer require sp4ceb4r/shell-command
```

## Running the tests

```
phpunit tests
```
