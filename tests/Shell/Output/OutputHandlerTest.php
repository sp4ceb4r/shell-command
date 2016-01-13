<?php

namespace Shell\Output;


/**
 * Class OutputHandlerTest
 */
class OutputHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var OutputHandler
     */
    protected $handler;

    public function setUp()
    {
        $this->handler = new OutputHandler();
    }

    /**
     * @dataProvider nullInputDataProvider
     */
    public function test_handle_null_input($out, $err, $expectedOut, $expectedErr)
    {
        $this->handler->handle($out, $err);

        $this->assertEquals($expectedOut, $this->handler->readStdOut());
        $this->assertEquals($expectedErr, $this->handler->readStdErr());
    }

    public function test_handle_empty_input()
    {
        $this->handler->handle('', '');

        $this->assertEquals('', $this->handler->readStdOut());
        $this->assertEquals('', $this->handler->readStdErr());
    }

    /**
     * @dataProvider readDataProvider
     */
    public function test_readStdOutLines($stdout, $expected)
    {
        $this->handler->handle($stdout, null);

        $result = $this->handler->readStdOutLines();

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider readDataProvider
     */
    public function test_readStdErrLines($stderr, $expected)
    {
        $this->handler->handle(null, $stderr);

        $result = $this->handler->readStdErrLines();

        foreach ($expected as $index => $line) {
            $this->assertArrayHasKey($index, $result);
            $this->assertEquals($line, $result[$index]);
        }
    }

    public function nullInputDataProvider()
    {
        return [
            [null, 'haha', '', 'haha'],
            ['haha', null, 'haha', ''],
            [null, null, '', ''],
        ];
    }

    public function readDataProvider()
    {
        return [
            ["\n\n\n", []],
            ["text\ntext", ['text', 'text']],
            ["text\n  \ntext", ['text', 'text']],
            ["text\n  \ntext\n", ['text', 'text']],
            ["text\n\t\n\n  \ntext\n", ['text', 'text']],
        ];
    }
}