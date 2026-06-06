# Action Dispatcher Routing Map

## Overview

As of the delegate cleanup, all write/read action entry points now go through a single endpoint:

- `public/actions.php`

Routes are selected via query or form field:

- `action=<domain>.<operation>`

Example:

- `POST /public/actions.php?action=items.add_task`

---

## Route Table

### Maintenance

- `maintenance.upload` → `Moro\Http\Controllers\Maintenance\UploadController::handle`
- `maintenance.publish` → `Moro\Http\Controllers\Maintenance\PublishController::handle`
- `maintenance.get_task_card` → `Moro\Http\Controllers\Maintenance\GetTaskCardController::handle`
- `maintenance.feedback_submit` → `Moro\Http\Controllers\Maintenance\FeedbackSubmitController::handle`
- `maintenance.content` → `Moro\Http\Controllers\Maintenance\ContentController::handle`

### Items

- `items.update_item` → `Moro\Http\Controllers\Items\UpdateItemController::handle`
- `items.history_update` → `Moro\Http\Controllers\Items\HistoryUpdateController::handle`
- `items.history_read` → `Moro\Http\Controllers\Items\HistoryReadController::handle`
- `items.history_delete` → `Moro\Http\Controllers\Items\HistoryDeleteController::handle`
- `items.get_unit_options` → `Moro\Http\Controllers\Items\GetUnitOptionsController::handle`
- `items.download_manual` → `Moro\Http\Controllers\Items\DownloadManualController::handle`
- `items.delete_item` → `Moro\Http\Controllers\Items\DeleteItemController::handle`
- `items.complete_task` → `Moro\Http\Controllers\Items\CompleteTaskController::handle`
- `items.add_task` → `Moro\Http\Controllers\Items\AddTaskController::handle`
- `items.add_manual` → `Moro\Http\Controllers\Items\AddManualController::handle`
- `items.add_item` → `Moro\Http\Controllers\Items\AddItemController::handle`
- `items.add_history` → `Moro\Http\Controllers\Items\AddHistoryController::handle`

### Feedback

- `feedback.status` → `Moro\Http\Controllers\Feedback\StatusController::handle`
- `feedback.notes` → `Moro\Http\Controllers\Feedback\NotesController::handle`

### Homes

- `homes.save_listing_profile` → `Moro\Http\Controllers\Homes\SaveListingProfileController::handle`
- `homes.respond_inquiry` → `Moro\Http\Controllers\Homes\RespondInquiryController::handle`
- `homes.submit_ownership_verification` → `Moro\Http\Controllers\Homes\SubmitOwnershipVerificationController::handle`

### Seeker

- `seeker.submit_inquiry` → `Moro\Http\Controllers\Seeker\SubmitInquiryController::handle`

### Contractor

- `contractor.submit_work` → `Moro\Http\Controllers\Contractor\ContractorSubmitWorkController::handle`
- `contractor.save_profile` → `Moro\Http\Controllers\Contractor\ContractorProfileController::handleSave`
- `contractor.review_submission` → `Moro\Http\Controllers\Contractor\OwnerReviewSubmissionController::handle`
- `contractor.jobs_list` → `Moro\Http\Controllers\Contractor\JobsController::listMine`
- `contractor.create_job` → `Moro\Http\Controllers\Contractor\OwnerCreateJobController::handle`
- `contractor.submit_verification` → `Moro\Http\Controllers\Contractor\SubmitVerificationController::handle`

### Navigation

- `nav.switch_portal` → `Moro\Http\Controllers\Nav\SwitchPortalController::handle`

### Verification

- `verification.review_case` → `Moro\Http\Controllers\Admin\ReviewVerificationCaseController::handle`

---

## Notes

- Unknown action keys return JSON 404:
  - `{ "ok": false, "error": "unknown_action" }`
- Missing action returns HTTP 400 with `Missing action.`
- Keep all new action endpoints behind this dispatcher to avoid reintroducing delegate file sprawl.
