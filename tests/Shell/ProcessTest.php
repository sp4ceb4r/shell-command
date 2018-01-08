<?php

use Shell\Commands\Command;
use Shell\Output\OutputHandler;
use Shell\Process;


/**
 * Class ProcessTest
 */
class ProcessTest extends PHPUnit_Framework_TestCase
{
    public function test_pcntl_extension_loaded()
    {
        $this->assertTrue(extension_loaded('pcntl')); // Constants from this extension are used in Process
    }

    public function test_run()
    {
        $cmd = Command::make('ls')->withOptions(['-l']);
        $handler = new Accumulator();

        $process = new Process($cmd, null, $handler);
        $process->runAsync();
        $process->wait();

        $this->assertEquals(0, $process->getExitCode());
        $this->assertCount(0, $handler->stderr);
        $this->assertGreaterThan(0, count($handler->stdout));
        $this->assertFalse($process->isAlive());
    }

    public function test_run_with_exec()
    {
        $handler = new Accumulator();
        $process = new Process(Command::make('exec ls')->withArgs('-l'), null, $handler);

        $process->runAsync()->wait();

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

        $process = Process::make($cmd, $handler)->usingCwd($cwd)
                                      ->runAsync();
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
            ->onError(function() {})
            ->runAsync()
        ;
        $process->wait(2);

        $this->assertLessThan(5, time() - $start);
        $this->assertEquals(SIGTERM, $process->getSignal());
    }

    public function test_kill()
    {
        $start = time();

        $process = Process::make(Command::make('sleep')->withArgs(5))
            ->onError(function() {})
            ->runAsync()
        ;
        usleep(100000);
        $process->kill();

        $this->assertLessThan(5, time() - $start);
        $this->assertEquals(SIGTERM, $process->getSignal());
    }

    public function test_write_to_stdin_and_read_from_stdout()
    {
        $process = Process::make(Command::make('cat'), new OutputHandler());

        $process->runInteractive(Process::BLOCKING);
        fwrite($process->getStdin(), 'foo');
        fclose($process->getStdin());
        $output = stream_get_contents($process->getStdout());
        $process->wait();

        $this->assertSame('foo', $output);
    }

    public function test_use_resource_for_stdin()
    {
        $outputHandler = new OutputHandler();
        $process = Process::make(Command::make('cat'), $outputHandler);
        $process->setStdin(fopen(__FILE__, 'r'));

        $process->runAsync(Process::BLOCKING);
        $output = stream_get_contents($process->getStdout());
        $process->wait();

        $this->assertStringStartsWith('<?php', $output);
        $this->assertEmpty($outputHandler->readStdOut(), 'Expect no output in handler if stdout is specified');
    }

    public function test_change_stdin_of_running_process_will_fail()
    {
        $actual = null;
        $process = Process::make(Command::make('ls'), new OutputHandler());
        $process->runAsync();
        try {
            $process->setStdin(['pipe', 'w']);
        } catch (RuntimeException $e) {
            $actual = $e;
        }
        $process->wait();
        $this->assertInstanceOf(RuntimeException::class, $actual);
        $this->assertSame('Process already running.', $actual->getMessage());
    }

    public function test_use_file_descriptor_for_stdin()
    {
        $process = Process::make(Command::make('cat'), new OutputHandler());
        $process->setStdin(['file', __FILE__, 'r']);
        $process->runAsync(Process::BLOCKING);
        $output = stream_get_contents($process->getStdout(), 5);
        $process->wait();
        $this->assertSame('<?php', $output);
    }

    public function test_use_file_descriptor_for_stdout()
    {
        $process = Process::make(Command::make('ls'), new OutputHandler());
        $process->setStdout(['file', '/dev/null', 'w']);
        $process->run();
    }

    public function test_use_resource_for_stdout()
    {
        $process = Process::make(Command::make('ls'), new OutputHandler());
        $process->setStdout(fopen('/dev/null', 'w'));
        $process->run();
    }
}
