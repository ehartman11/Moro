<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Response;

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === '') {
    http_response_code(400);
    exit('Missing action.');
}

$routes = [
    'maintenance.upload' => [\Moro\Http\Controllers\Maintenance\UploadController::class, 'handle'],
    'maintenance.publish' => [\Moro\Http\Controllers\Maintenance\PublishController::class, 'handle'],
    'maintenance.get_task_card' => [\Moro\Http\Controllers\Maintenance\GetTaskCardController::class, 'handle'],
    'maintenance.feedback_submit' => [\Moro\Http\Controllers\Maintenance\FeedbackSubmitController::class, 'handle'],
    'maintenance.content' => [\Moro\Http\Controllers\Maintenance\ContentController::class, 'handle'],

    'items.update_item' => [\Moro\Http\Controllers\Items\UpdateItemController::class, 'handle'],
    'items.history_update' => [\Moro\Http\Controllers\Items\HistoryUpdateController::class, 'handle'],
    'items.history_read' => [\Moro\Http\Controllers\Items\HistoryReadController::class, 'handle'],
    'items.history_delete' => [\Moro\Http\Controllers\Items\HistoryDeleteController::class, 'handle'],
    'items.get_unit_options' => [\Moro\Http\Controllers\Items\GetUnitOptionsController::class, 'handle'],
    'items.download_manual' => [\Moro\Http\Controllers\Items\DownloadManualController::class, 'handle'],
    'items.delete_item' => [\Moro\Http\Controllers\Items\DeleteItemController::class, 'handle'],
    'items.complete_task' => [\Moro\Http\Controllers\Items\CompleteTaskController::class, 'handle'],
    'items.add_task' => [\Moro\Http\Controllers\Items\AddTaskController::class, 'handle'],
    'items.add_manual' => [\Moro\Http\Controllers\Items\AddManualController::class, 'handle'],
    'items.add_item' => [\Moro\Http\Controllers\Items\AddItemController::class, 'handle'],
    'items.add_history' => [\Moro\Http\Controllers\Items\AddHistoryController::class, 'handle'],

    'feedback.status' => [\Moro\Http\Controllers\Feedback\StatusController::class, 'handle'],
    'feedback.notes' => [\Moro\Http\Controllers\Feedback\NotesController::class, 'handle'],

    'homes.save_listing_profile' => [\Moro\Http\Controllers\Homes\SaveListingProfileController::class, 'handle'],
    'homes.respond_inquiry' => [\Moro\Http\Controllers\Homes\RespondInquiryController::class, 'handle'],
    'homes.submit_ownership_verification' => [\Moro\Http\Controllers\Homes\SubmitOwnershipVerificationController::class, 'handle'],

    'seeker.submit_inquiry' => [\Moro\Http\Controllers\Seeker\SubmitInquiryController::class, 'handle'],

    'contractor.submit_work' => [\Moro\Http\Controllers\Contractor\ContractorSubmitWorkController::class, 'handle'],
    'contractor.save_profile' => [\Moro\Http\Controllers\Contractor\ContractorProfileController::class, 'handleSave'],
    'contractor.review_submission' => [\Moro\Http\Controllers\Contractor\OwnerReviewSubmissionController::class, 'handle'],
    'contractor.jobs_list' => [\Moro\Http\Controllers\Contractor\JobsController::class, 'listMine'],
    'contractor.create_job' => [\Moro\Http\Controllers\Contractor\OwnerCreateJobController::class, 'handle'],
    'contractor.submit_verification' => [\Moro\Http\Controllers\Contractor\SubmitVerificationController::class, 'handle'],

    'nav.switch_portal' => [\Moro\Http\Controllers\Nav\SwitchPortalController::class, 'handle'],
    'verification.review_case' => [\Moro\Http\Controllers\Admin\ReviewVerificationCaseController::class, 'handle'],
];

if (!isset($routes[$action])) {
    Response::json(['ok' => false, 'error' => 'unknown_action'], 404);
}

[$className, $method] = $routes[$action];
$controller = new $className();
$controller->{$method}();
