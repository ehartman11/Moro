# Verification Roadmap (Draft)

## Purpose
This draft defines a phased verification strategy for Moro so product, engineering, and operations can implement trust features incrementally without blocking growth.

## Scope Covered
- Homeowner ownership verification
- Contractor/handyman identity and capability verification
- Ownership transfer workflow (seller -> buyer)
- Contractor selection trust signals and filtering
- Optional external data seeding/integrations

## Design Principles
1. **Progressive trust, not hard lockout:** users can onboard before verification, but sensitive actions are gated.
2. **Explainability:** every verification decision has status, reason, and next steps.
3. **Auditability:** all state changes are logged with actor and timestamp.
4. **Least-friction MVP first:** manual review first, third-party API verification later.
5. **Neutral inclusion:** handymen are allowed; licensing requirements are policy-driven by service type and location.

## Shared Status Model
Use common lifecycle terms for both homes and contractors:
- `unverified`: profile/entity created, no proof submitted
- `pending_review`: proofs submitted, waiting review
- `verified`: approved by reviewer/system
- `rejected`: denied; remediation required
- `revoked`: previously verified but invalidated (fraud/expiration)

Optional implementation detail:
- Keep a **current status** on subject records for fast checks
- Keep a **history table** for status transitions and reasons

---

## Phase 0 - Policy + Data Foundations (Planning)
### Objective
Establish trust policy and database structures before feature gating.

### Deliverables
- Verification policy matrix (what requires verification, by role/action)
- Canonical status enums and transition rules
- Data model + migration plan
- Privacy/retention policy for uploaded documents

### Proposed Core Tables (draft)
- `verification_cases`
  - `id`, `subject_type` (`home_owner_claim` | `contractor_profile`), `subject_id`, `status`, `submitted_by_user_id`, `reviewed_by_user_id`, `review_notes`, `created_at`, `updated_at`
- `verification_documents`
  - `id`, `case_id`, `doc_type`, `doc_key`, `mime_type`, `redaction_status`, `created_at`
- `verification_events`
  - `id`, `case_id`, `from_status`, `to_status`, `actor_user_id`, `reason_code`, `notes`, `created_at`
- `ownership_transfers`
  - `id`, `home_id`, `seller_user_id`, `buyer_user_id`, `status`, `initiated_at`, `accepted_at`, `completed_at`, `cancelled_at`, `reviewed_by_user_id`, `notes`

### Exit Criteria
- Policy approved
- Migrations drafted and reviewed
- Internal terminology frozen

---

## Phase 1 - Manual Verification MVP (Home + Contractor)
### Objective
Ship first trust layer with manual review queue and visible verification badges.

### Homeowner Verification Flow
1. User claims/creates home
2. User submits ownership proof (1+ allowed document types)
3. Case enters `pending_review`
4. Reviewer approves/rejects
5. User sees status + remediation guidance

### Contractor Verification Flow
1. Contractor completes profile and uploads proof (license/insurance optional by policy)
2. Case enters `pending_review`
3. Reviewer approves/rejects
4. Profile shows trust badge (`verified` vs `unverified`)

### Product Gating (initial)
- Allow unverified users to onboard and browse
- Require `verified` for:
  - ownership transfer completion
  - premium trust badge display
- Optional stricter gate later:
  - only verified contractors eligible for certain job types

### UI Surfaces
- User-facing status chips on homes and contractor cards
- Submission screen for proofs
- Admin review queue with approve/reject actions
- Rejection reason and resubmit CTA

### API/Action Draft (naming placeholder)
- `verification.submit_case`
- `verification.upload_document`
- `verification.admin.review_case`
- `verification.case_status`

### Exit Criteria
- End-to-end claim -> submit -> review -> status update working
- Audit events generated for all decisions
- At least basic admin queue available

---

## Phase 2 - Ownership Transfer Workflow
### Objective
Make home ownership transfer safe and explicit between seller and buyer.

### Workflow
1. Seller initiates transfer to buyer account/email
2. Buyer accepts invitation
3. Required evidence validated (policy-driven)
4. Reviewer/system finalizes transfer
5. `home_permissions` updates ownership role atomically
6. Full transfer log retained

### States (proposed)
- `initiated` -> `buyer_pending` -> `review_pending` -> `completed`
- Alternate terminal: `cancelled`, `rejected`, `expired`

### Guardrails
- Current owner remains owner until transfer completes
- Prevent duplicate concurrent active transfer on same home
- Dispute/fraud hold path before completion

### Exit Criteria
- Transfer can be initiated, accepted, reviewed, completed, and audited
- Role change is atomic and reversible only by admin workflow

---

## Phase 3 - Trust-Aware Matching and Selection UX
### Objective
Improve owner decision quality when assigning contractors.

### Contractor Selection Enhancements
- Filter by:
  - service category/expertise
  - operating/license state (later radius/zip)
  - verification status
- Sorting defaults:
  - verified first
  - then relevance/category match

### Trust Signals in UI
- Badge variants: `Verified`, `Pending`, `Unverified`, `Revoked`
- Tooltip/help text explaining badge meaning
- Warning banner when assigning unverified provider

### Item/Task Assignment UX
- Keep optional item/task linkage
- Use item dropdown and dependent task dropdown (already started)
- Add validation messaging when task not tied to selected item

### Exit Criteria
- Owner assignment flow shows trust-aware filtering
- Badges are consistent across contractor views and job creation

---

## Phase 4 - External Data + Semi-Automated Verification
### Objective
Reduce manual burden and improve data quality via optional integrations.

### Homes Data Strategy
- Optional import/seed from external listing datasets
- Imported homes start as `unclaimed`/`unverified`
- Ownership requires claim + verification before sensitive actions

### Contractor Data Strategy
- Optional enrichment from business directories/state registries
- Keep confidence score and source provenance
- Never auto-mark fully verified without policy-approved rule

### Automation Targets
- Auto-check for document completeness
- Auto-expire stale verification artifacts
- Flag suspicious patterns for review

### Exit Criteria
- At least one external source integrated behind feature flag
- Provenance + confidence visible internally

---

## Phase 5 - Risk, Compliance, and Operations Hardening
### Objective
Scale review operations and tighten abuse controls.

### Controls
- SLA dashboards for review throughput/latency
- Fraud heuristics (multi-claim collisions, repeated rejects, reused docs)
- Revocation workflows and incident playbooks
- Retention and deletion automation for PII documents

### Metrics (suggested)
- Verification completion rate
- Median time-to-verify
- Rejection and resubmission rates
- Transfer success rate and dispute rate
- Job outcomes by contractor verification status

### Exit Criteria
- Operational runbook in place
- Alerting and audit exports available

---

## Open Questions (To Resolve Before Build Lock)
1. Which actions are hard-gated by `verified` in v1 vs soft-warned?
2. What minimum document set is required per region for homeowner claims?
3. Do handymen require different verification steps than licensed contractors?
4. What retention window applies to uploaded verification documents?
5. Which team members can review/approve/revoke, and with what dual-control rules?

## Recommended Implementation Order
1. Phase 0 foundations
2. Phase 1 manual verification MVP
3. Phase 2 ownership transfer
4. Phase 3 trust-aware matching UX
5. Phase 4 integrations
6. Phase 5 operations hardening

## Notes
- This document is a draft reference and intentionally separates policy from implementation details.
- All new gates should ship behind feature flags where practical.
