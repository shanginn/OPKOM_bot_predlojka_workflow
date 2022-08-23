<?php

declare(strict_types=1);

$currentDir = __DIR__;

require $currentDir . '/../vendor/autoload.php';

use Temporal\Testing\Environment;

$environment = Environment::create();

echo "./rr serve -c $currentDir/.rr.test.yaml -w tests";
$environment->start("./rr serve -c $currentDir/.rr.test.yaml -w tests");
register_shutdown_function(fn () => $environment->stop());
