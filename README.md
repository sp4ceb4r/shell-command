<p align="center">
    <a href="https://travis-ci.org/sp4ceb4r/shell-command">
        <img src="https://travis-ci.org/sp4ceb4r/shell-command.svg" alt="Build Status">
    </a>
    <a href="https://packagist.org/packages/sp4ceb4r/shell-command">
        <img src="https://poser.pugx.org/sp4ceb4r/shell-command/license.svg" alt="License">
    </a>
</p>

# shell-command
[![Build Status](https://travis-ci.org/sp4ceb4r/shell-command.svg?branch=v0.2.0)](https://travis-ci.org/sp4ceb4r/shell-command)

A simple wrapper of the php proc_open command.

Simplifies running and interacting with shell commands within a php script.


## Getting Started

```php
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
