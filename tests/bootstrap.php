<?php

$loader = require_once(__DIR__.'/../vendor/autoload.php');

if (!$loader = @include __DIR__.'/../vendor/autoload.php') {
    exit(1);
}

$loader->add('tests', __DIR__);
