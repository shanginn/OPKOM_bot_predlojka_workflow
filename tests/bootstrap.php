<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Temporal\Testing\Environment;

(Dotenv::createImmutable(__DIR__ . '/../', '.env.testing'))
    ->load();

$environment = Environment::create();
$environment->start("./rr serve -c .rr.test.yaml -w tests");

register_shutdown_function(fn () => $environment->stop());
