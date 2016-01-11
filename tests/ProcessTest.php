<?php

use Process\Command;
use Process\Process;

/**
 * Class ProcessTest
 */
class ProcessTest extends PHPUnit_Framework_TestCase
{
    public function test_run()
    {
        $cmd = Command::command('ls')->withOptions(['-l']);
        $process = new Process($cmd);

        $process->run();

        $out = $process->readStdOut();
        $err = $process->readStdErr();

        $this->assertEquals(0, $process->getExitCode());
        $this->assertEmpty($err);
        $this->assertNotEmpty($out);
        $this->assertFalse($process->killed());
        $this->assertFalse($process->isAlive());
    }

    public function test_run_with_exec()
    {
        $process = new Process(Command::command('exec ls')->withArgs('-l'));

        $process->run();

        $out = $process->readStdOut();
        $err = $process->readStdErr();

        $this->assertEquals(0, $process->getExitCode());
        $this->assertEmpty($err);
        $this->assertNotEmpty($out);
        $this->assertFalse($process->killed());
        $this->assertFalse($process->isAlive());
    }

    public function test_run_long_running_blocking()
    {
        $time = time();
        $process = Process::process(Command::command('sleep')->withArgs(5))->run();

        $diff = time() - $time;
        $this->assertTrue($diff >= 5);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertFalse($process->killed());
        $this->assertFalse($process->isAlive());
    }

    public function test_run_non_blocking()
    {
        $cwd = __DIR__.'/../../';

        $cmd = Command::command('find')->withArgs('.');
        $process = Process::process($cmd)->usingCwd($cwd)
                                         ->run(false);

        $out = [];
        $reads = 0;
        while ($process->isAlive()) {
            $out += explode("\n", $process->readStdOut());
            $reads++;
        }

        $this->assertTrue($reads > 0);
        $this->assertNotEmpty($out);

        $this->assertEquals(0, $process->getExitCode());
        $this->assertFalse($process->isAlive());
        $this->assertFalse($process->killed());
    }

    public function test_kill()
    {
        $start = time();
        $process = Process::process(Command::command('sleep')->withArgs(5))->run(false);;

        $process->kill();

        $this->assertLessThan(5, time() - $start);
        $this->assertTrue($process->killed());
        $this->assertEquals(SIGTERM, $process->getTermSig());
    }
}
