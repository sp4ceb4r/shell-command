<?php

namespace Shell\Commands;


/**
 * Interface CommandInterface
 */
interface CommandInterface
{
    static function make($name);

    public function withArgs();

    public function withOptions();

    public function serialize();

    public function validate();
}
