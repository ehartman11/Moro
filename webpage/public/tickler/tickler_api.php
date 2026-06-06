<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

(new \Moro\Http\Controllers\TicklerApiController())->handle();
