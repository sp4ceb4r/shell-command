<?php

use Shell\Commands\Command;
use Shell\Process;


/**
 * Class ProcessTest
 */
class ProcessTest extends PHPUnit_Framework_TestCase
{
    public function test_run()
    {
        $cmd = Command::make('ls')->withOptions(['-l']);
        $handler = new Accumulator();

        $process = new Process($cmd);
        $process->runAsync($handler);
        $process->wait();

        $this->assertEquals(0, $process->getExitCode());
        $this->assertCount(0, $handler->stderr);
        $this->assertGreaterThan(0, count($handler->stdout));
        $this->assertFalse($process->isAlive());
    }

    public function test_run_with_exec()
    {
        $handler = new Accumulator();
        $process = new Process(Command::make('exec ls')->withArgs('-l'));

        $process->runAsync($handler)->wait();

        $this->assertEquals(0, $process->getExitCode());
        $this->assertEmpty($handler->stderr);
        $this->assertNotEmpty($handler->stdout);
        $this->assertFalse($process->isAlive());
    }

    public function test_run_long_running_blocking()
    {
        $time = time();

        $process = Process::make(Command::make('sleep')->withArgs(5))->runAsync();
        $process->wait();

        $diff = time() - $time;
        $this->assertTrue($diff >= 5);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertFalse($process->isAlive());
    }

    public function test_run_non_blocking()
    {
        $cwd = __DIR__.'/../../';

        $cmd = Command::make('find')->withArgs('.');
        $handler = new ReadCounter();

        $process = Process::make($cmd)->usingCwd($cwd)
                                      ->runAsync($handler);
        $process->wait();

        $this->assertGreaterThan(0, $handler->reads);
        $this->assertNotEmpty($handler->stdout);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertFalse($process->isAlive());
    }

    public function test_wait_timeout()
    {
        $start = time();

        $process = Process::make(Command::make('sleep')->withArgs(5))
                                                       ->runAsync();;
        $process->wait(2);

        $this->assertLessThan(5, time() - $start);
        $this->assertEquals(SIGTERM, $process->getSignal());
    }

    public function test_kill()
    {
        $start = time();

        $process = Process::make(Command::make('sleep')->withArgs(5))
                                                       ->runAsync();;
        usleep(100000);
        $process->kill();

        $this->assertLessThan(5, time() - $start);
        $this->assertEquals(SIGTERM, $process->getSignal());
    }
}
