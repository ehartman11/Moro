# Hyper-Productivity AI Experiment Plan (Moro Project)

## Assignment Fit

This plan uses the current Moro codebase as the profession/project area and compares classic workflow versus AI-assisted workflow using measurable tasks from owner, contractor, and maintenance operations.

## Profession Area

Property and maintenance operations software development and QA (Moro platform).

## Hyper-Productivity Goal

Increase delivery speed and test coverage quality for production-like workflows in Moro by using AI for:

- action routing analysis
- smoke test generation
- bug triage and root-cause hints
- SQL/repository query drafting
- release note drafting

## Project Scope Used

This experiment intentionally anchors to active routes and pages already present in this repo:

- Dispatcher endpoint: `webpage/public/actions.php`
- Security/role guard: `webpage/src/core/Auth.php`
- Contractor workflow pages:
  - `webpage/public/contractor/my_jobs.php`
  - `webpage/public/contractor/submissions.php`
- Feedback owner page:
  - `webpage/public/feedback/index.php`
- Existing QA baseline checklist:
  - `webpage/manuals/Smoke_Test_Checklist.md`

## What To Measure (AI vs Classical)

Run the same task set twice:

1. Classical run (no AI help)
2. AI-assisted run (Copilot/LLM used)

Capture the following metrics:

- cycle_time_sec: seconds from task start to accepted output
- throughput_per_hour: tasks completed per hour
- defect_count: number of corrections needed after first pass
- rework_sec: additional seconds spent fixing output
- quality_score_1_to_5: self-rubric score for completeness + correctness

Formulas:

- productivity = completed_tasks / elapsed_hours
- time_reduction_percent = ((classic_time - ai_time) / classic_time) * 100
- hyper_productivity_factor = ai_productivity / classic_productivity

## Experiment Task Set

Use 12 tasks total (repeatable in one session):

### A) Routing and Endpoint Analysis (4 tasks)

1. Map all contractor action keys and controllers from `actions.php`.
2. Identify owner-only routes and expected role checks.
3. Detect missing/unknown action behavior and expected API result.
4. Draft one regression test checklist update for a changed route.

### B) Contractor Flow QA (4 tasks)

1. Validate submit-work happy path from `my_jobs.php` form fields.
2. Validate reject/needs_changes path and comment requirement in `submissions.php`.
3. Verify media preview behavior for image vs non-image files.
4. Create a bug report template populated from one simulated failure.

### C) Security and Auth Reasoning (2 tasks)

1. Explain where owner gating is enforced for feedback and submissions.
2. Produce an access-matrix snippet for owner/contractor/viewer/seeker roles.

### D) Reporting and Documentation (2 tasks)

1. Write concise release notes for one contractor workflow improvement.
2. Draft an executive summary of QA findings for non-technical stakeholders.

## AI Usage Rules (Show in Video)

For each AI-assisted task, capture:

- prompt used
- raw AI output
- your edits/verification steps
- final accepted output and elapsed time

This satisfies the requirement to show exactly what AI did.

## Prompt Templates

### Prompt 1: Route Mapping

"Read `webpage/public/actions.php` and extract all action keys under `contractor.*`. Return a table with action key, controller class, method, and one sentence risk if misconfigured."

### Prompt 2: QA Test Generation

"Using `webpage/public/contractor/my_jobs.php` and `webpage/public/contractor/submissions.php`, generate smoke tests for happy path, invalid media type, missing CSRF, and unauthorized owner access."

### Prompt 3: Security Review

"Trace owner authorization checks in `webpage/public/feedback/index.php` and contractor submission review flow. List where role checks occur and what redirect/error code is expected."

### Prompt 4: Stakeholder Summary

"Convert these technical test results into a concise manager summary with risks, impact, and recommended next steps."

## Data Collection Method

Use the CSV sheet:

- `webpage/manuals/Hyper_Productivity_Measurement_Template.csv`

Log each task in both modes:

- mode = classic or ai
- same task_id and task_name for comparability

## Video Recording Structure (3-5 min)

### 0:00-0:30 Introduction

- State assignment goal and why Moro is the chosen professional project.

### 0:30-1:15 Classical Run Snapshot

- Show one or two tasks done manually.
- Show timer and outputs.

### 1:15-2:30 AI-Assisted Run Snapshot

- Show prompts and AI output.
- Show your verification and fixes.

### 2:30-3:20 Measurement Results

- Present totals: time, throughput, defects, rework.
- Show hyper-productivity factor.

### 3:20-4:00 Reflection

- Faster than expected with AI.
- Slower/harder than expected with AI.
- What remains human-critical.

## Suggested Rubric for Quality Score

- 5 = correct, complete, production-ready with minimal edits
- 4 = mostly correct, minor edits required
- 3 = partially correct, moderate edits required
- 2 = significant issues, high rework
- 1 = unusable output

## Expected Findings Pattern (Typical)

Likely faster with AI:

- first-draft documentation
- route extraction and summarization
- test-case drafting

Likely slower than expected:

- validating AI assumptions against actual code
- correcting subtle domain logic mistakes
- producing final polished artifacts

## Final Submission

Submit a YouTube link that includes:

- your face and narration
- visible Moro files and AI prompts/outputs
- timing evidence and comparison table
- quantified outcome and reflection
