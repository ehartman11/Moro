<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Http\Controllers\FeedbackController;

$pdo = Db::pdo();

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$role   = Auth::roleOnHome($pdo, $userId, $homeId);

$returnTo = $_POST['return_to'] ?? (Paths::baseUrl() . '/public/homes.php');

Auth::requireOwner($role, $returnTo);

$controller = new FeedbackController($pdo);
$vm = $controller->index($homeId, $_GET);

require __DIR__ . '/_index.view.php';