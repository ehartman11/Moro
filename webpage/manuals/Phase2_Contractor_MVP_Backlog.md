# Phase 2 Contractor Portal MVP Backlog

## Scope Anchor
This backlog operationalizes [manuals/Moro_Three_Portal_Blueprint.md](manuals/Moro_Three_Portal_Blueprint.md) for **Phase 2 (6-14 weeks)**.

## Primary Outcome
Enable end-to-end contractor -> homeowner submission/review flow where approved work is written into homeowner history with verifiable artifacts.

---

## Epic A: Data Foundation (Week 1-2)
## Goal
Create persistent model for contractor jobs, submissions, media, and reviews.

### Stories
- A1: Add `contractor_profiles` table linked to users.
- A2: Add `service_jobs` table for homeowner-contractor work assignments.
- A3: Add `job_submissions` table for receipts/work proofs.
- A4: Add `submission_media` table for before/after evidence.
- A5: Add `submission_reviews` table for approval/rejection audit trail.
- A6: Add indexes and FK constraints for query + integrity.

### Deliverables
- SQL migration: [data/migrations/2026_03_05_phase2_contractor_portal.sql](data/migrations/2026_03_05_phase2_contractor_portal.sql)
- Repository interfaces for each new aggregate.

### Acceptance
- Migration runs cleanly in local env.
- FK and state constraints enforce expected lifecycle integrity.

---

## Epic B: Contractor Identity + Access (Week 2-3)
## Goal
Allow users to become contractors and manage profile.

### Stories
- B1: Add contractor profile create/edit page.
- B2: Add role gating helper for contractor-only actions.
- B3: Add nav entry and portal landing for contractors.

### Deliverables
- `ContractorProfileController`
- `ContractorRepository` + `ContractorService`
- UI page in `public/contractor/`

### Acceptance
- Existing user can enroll as contractor and update profile.
- Non-contractors cannot access contractor-only endpoints.

---

## Epic C: Homeowner Job Assignment (Week 3-5)
## Goal
Homeowners create and manage contractor jobs.

### Stories
- C1: Homeowner creates service job from item/task context.
- C2: Homeowner assigns job to contractor by profile.
- C3: Job states: `open -> assigned -> in_progress -> completed/cancelled`.
- C4: Homeowner dashboard list + filters (state, item, contractor).

### Deliverables
- `ServiceJobController` (owner endpoints)
- `ServiceJobRepository` + `ServiceJobService`
- Owner job views under `public/items/` or `public/jobs/`

### Acceptance
- Owner can create job and assign contractor.
- Only owner/member for home can view job board.

---

## Epic D: Contractor Submissions (Week 5-7)
## Goal
Contractors submit proof-of-work for assigned jobs.

### Stories
- D1: Contractor sees assigned jobs.
- D2: Contractor creates submission with amount, summary, receipt.
- D3: Contractor uploads media evidence (before/after).
- D4: Contractor can resubmit after homeowner requests changes.

### Deliverables
- `JobSubmissionController` (contractor endpoints)
- Storage paths in `storage/submissions/`
- Validation rules (file type/size, amount, required fields)

### Acceptance
- Submission lifecycle: `draft -> submitted -> needs_changes -> resubmitted`.
- Submission cannot target unrelated home/job.

---

## Epic E: Homeowner Review Queue (Week 7-9)
## Goal
Homeowners review and decide submission outcomes.

### Stories
- E1: Review queue with pending submissions.
- E2: Decision actions: `approve`, `reject`, `needs_changes`.
- E3: Mandatory review note on reject/needs_changes.
- E4: Immutable review event log.

### Deliverables
- `SubmissionReviewController`
- `submission_reviews` write path
- Owner queue UI + detail panel

### Acceptance
- Review decision updates submission state.
- Full review history visible for audit.

---

## Epic F: Canonical History Write-Back (Week 9-11)
## Goal
Approved submissions create official homeowner history entries.

### Stories
- F1: On approval, append to `task_history` (or companion contractor history table if task absent).
- F2: Link approved submission metadata in history note/proof references.
- F3: Attach approved media to history context when possible.
- F4: Ensure idempotency (approval processed once).

### Deliverables
- `SubmissionApprovalService` transaction flow
- Mapping logic to item/task/home ownership

### Acceptance
- Approved work appears in homeowner history timeline.
- Re-approving same submission does not duplicate writes.

---

## Epic G: Notifications + Transparency (Week 11-12)
## Goal
Improve communication across actors.

### Stories
- G1: Job assigned notification to contractor.
- G2: Submission received notification to homeowner.
- G3: Decision notification to contractor.

### Deliverables
- In-app notification table + service (email optional).

### Acceptance
- Status changes are visible to both parties without manual refresh confusion.

---

## Epic H: Stabilization + Security Hardening (Week 12-14)
## Goal
Production-ready quality for contractor MVP.

### Stories
- H1: CSRF/role/access audit for all new endpoints.
- H2: Add integration tests for critical workflow.
- H3: Add structured logs around submission/review transitions.
- H4: Add admin troubleshooting queries/runbook.

### Acceptance
- No privilege escalation paths in contractor flow.
- Core e2e tests pass reliably.

---

## API/Controller Skeleton (Planned)
- `src/Http/Controllers/Contractor/ProfileController.php`
- `src/Http/Controllers/Jobs/ServiceJobController.php`
- `src/Http/Controllers/Jobs/JobSubmissionController.php`
- `src/Http/Controllers/Jobs/SubmissionReviewController.php`

- `src/Repositories/ContractorRepository.php`
- `src/Repositories/ServiceJobRepository.php`
- `src/Repositories/JobSubmissionRepository.php`
- `src/Repositories/SubmissionReviewRepository.php`

- `src/Services/ContractorService.php`
- `src/Services/ServiceJobService.php`
- `src/Services/JobSubmissionService.php`
- `src/Services/SubmissionApprovalService.php`

---

## Definition of Done (Phase 2)
- Contractor can onboard and receive assigned jobs.
- Contractor can submit receipt + proof.
- Homeowner can approve/reject with audit trail.
- Approval writes canonical history evidence.
- Security and role boundaries verified.
- Backward compatibility with existing homeowner portal preserved.
