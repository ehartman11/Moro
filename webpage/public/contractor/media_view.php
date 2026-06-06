<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Http\Controllers\Contractor\SubmissionMediaController;

Auth::requireLogin();
$homeId = Auth::requireActiveHome();

(new SubmissionMediaController())->view($homeId, $_GET);
