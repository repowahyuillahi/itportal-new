<?php

declare(strict_types=1);

/**
 * Front controller. Keep it thin. All wiring happens in bootstrap.php.
 */

$dispatch = require __DIR__ . '/../bootstrap.php';

$request = new App\Core\Request();
echo $dispatch($request);
