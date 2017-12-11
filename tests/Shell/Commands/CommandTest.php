<?php

use Shell\Commands\Command;

/**
 * Class CommandTest
 */
class CommandTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException RuntimeException
     */
    public function test_validate_empty_binary()
    {
        $cmd = new Command("  \t ");
        $cmd->validate();
    }

    /**
     * @expectedException RuntimeException
     */
    public function test_validate_non_file_binary()
    {
        $cmd = new Command(__DIR__);
        $cmd->validate();
    }

    /**
     * @expectedException RuntimeException
     */
    public function test_validate_non_executable_binary()
    {
        $cmd = new Command(__FILE__);
        $cmd->validate();
    }

    /**
     * @expectedException RuntimeException
     */
    public function test_validate_multiple_nargs()
    {
        $opts = [
            '--n1' => ['a','b'],
            '--n2' => [1,2],
        ];
        $cmd = Command::make($_SERVER['PHP_SELF'])->withOptions($opts);
        $cmd->validate();
    }

    /**
     * @expectsNoException
     */
    public function test_validate()
    {
        $cmd = new Command($_SERVER['PHP_SELF']);
        $cmd->validate();
    }

    /**
     * @expectsNoException
     */
    public function test_validate_path_command()
    {
        $cmd = new Command('ls');
        $cmd->validate();
    }

    public function test_serialize_no_args_no_opts()
    {
        $cmd = new Command('phpunit');

        $this->assertEquals('phpunit', substr($cmd->serialize(), 0-strlen('phpunit')));
    }

    public function test_serialize_with_args()
    {
        $args = ['a', 'b', 'c'];

        $cmd = Command::make('phpunit')->withArgs($args);

        $expected = 'phpunit a b c';
        $this->assertEquals($expected, substr($cmd->serialize(), 0-strlen($expected)));
    }

    public function test_serialize_with_opts()
    {
        $opts = [
            '--long' => null,
            '--array' => ['f', 'g'],
            '--single' => 'z',
        ];

        $cmd = Command::make('phpunit')->withOptions($opts);

        $expected = 'phpunit --long --single z --array f g';
        $this->assertEquals($expected, substr($cmd->serialize(), 0-strlen($expected)));
    }

    public function test_serialize()
    {
        $args = ['a', 'b', 'c'];
        $opts = [
            '--long' => null,
            '--array' => ['f', 'g'],
            '--single' => 'z',
        ];

        $cmd = new Command('phpunit', $args, $opts);

        $expected = 'phpunit a b c --long --single z --array f g';
        $this->assertEquals($expected, substr($cmd->serialize(), 0-strlen($expected)));
    }

    public function test_serialize_nameless_option()
    {
        $options = [
            '--' => ['/tmp/1.txt', '/tmp/2.txt'],
        ];

        $cmd = new Command($_SERVER['PHP_SELF'], [], $options);
        $this->assertEquals("{$_SERVER['PHP_SELF']} -- /tmp/1.txt /tmp/2.txt", $cmd->serialize());
    }
}
