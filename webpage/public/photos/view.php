<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Http\Controllers\PhotoController;
use Moro\Repositories\PhotoRepository;

$pdo = Db::pdo();
$photoRepo = new PhotoRepository($pdo);

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();

$controller = new PhotoController($photoRepo);
$controller->view($homeId, $_GET); // echoes + exits