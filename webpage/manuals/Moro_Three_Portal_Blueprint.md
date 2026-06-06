# Moro Three-Portal Blueprint (v1)

## 1) Product Vision
Moro is a **home stewardship trust platform** with three connected portals:
- **Homeowner Portal**: track assets, maintenance, receipts, photos, manuals, and lifecycle history.
- **Contractor Portal**: submit verifiable proof of work and communicate scope/completion transparently.
- **Home Seeker Portal**: view permissioned maintenance history and confidence signals before purchase.

### North-Star Outcome
Reduce uncertainty and increase trust in home ownership, maintenance quality, and resale value.


---

## 2) Strategic Product Thesis
Most systems optimize for listing discovery (marketplaces) or personal reminders (to-do apps). Moro differentiates by optimizing for **verifiable maintenance truth over time**.

### Core Value by Persona
- **Homeowner**: better planning, fewer surprise failures, stronger resale narrative.
- **Contractor**: credibility, repeat work, cleaner client communication, portfolio effect.
- **Home Seeker**: lower risk, evidence-backed decisions, better valuation confidence.

---

## 3) Platform Scope (Global)

## 3.1 Current Scope (already strong)
- User auth and home context
- Asset and maintenance task management
- Material/service history with photos/manuals
- MRC revisioning + feedback workflow
- Tickler/countdown scheduling

## 3.2 Target Scope (multi-portal)
- Role-aware workspace by actor: homeowner / contractor / seeker
- Trust workflows: submission -> review -> approve/reject -> immutable history record
- Shareability controls: private, invited, listing-safe summary, due-diligence packet

---

## 4) Product Capabilities by Portal

## 4.1 Homeowner Portal (expand existing)
### MVP+ capabilities
- Item + component registry per home
- Task scheduling + completion history
- Contractor invitation and job assignment
- Work submission inbox (review queue)
- Approval/rejection with comments
- Listing share mode and curated disclosure package

### Differentiators
- Confidence score (maintenance completeness + recency + documentation quality)
- Home timeline (major systems, repairs, renovations)

## 4.2 Contractor Portal
### Core capabilities
- Contractor account + profile + service categories
- Job intake from homeowner (task, scope, due date)
- Submission payload:
  - receipt/invoice
  - work note
  - photos (before/after)
  - optional warranty metadata
- Status lifecycle:
  - Draft -> Submitted -> Needs Clarification -> Approved/Rejected
- Revision and resubmission support

### Trust mechanics
- homeowner must approve before entry becomes canonical in material history
- immutable audit trail (who submitted, who approved, when)

## 4.3 Home Seeker Portal
### Core capabilities
- home listing profile with owner-controlled visibility
- maintenance timeline summary
- key system status cards (roof/HVAC/plumbing/electrical/appliances)
- verified work proofs and contractor attribution (if owner permits)
- inquiry/contact workflow with owner

### Privacy controls
- exact receipts and private notes may be hidden
- personal data always redacted
- owner chooses disclosure tier per listing

---

## 5) Domain Model (Target)
Use current data model as baseline; add entities for cross-portal trust flows.

### Existing foundations (already aligned)
- users, homes, home_permissions
- items, maintenance_tasks, task_schedule, task_history, photos
- mrc_content, mrc_feedback

### New/expanded entities
- **contractors**
  - user_id, business_name, license/insurance metadata, profile fields
- **service_jobs**
  - home_id, item_id, task_id (nullable), homeowner_id, contractor_id, state, scheduled_at
- **job_submissions**
  - job_id, submitted_by, amount, currency, receipt_doc_key, work_summary, submitted_at, state
- **submission_media**
  - submission_id, media_key, type, caption
- **submission_reviews**
  - submission_id, reviewer_user_id, decision(approve/reject/needs_changes), comments, reviewed_at
- **home_disclosure_profiles**
  - home_id, visibility_tier, public_summary, last_published_at
- **disclosure_artifacts**
  - profile_id, artifact_type, source_refs, generated_at
- **home_inquiries**
  - home_id, seeker_user_id, message, state, opened_at, responded_at

### Data rule
No contractor-submitted work enters canonical homeowner history until approved.

---

## 6) Trust, Permissions, and Safety Model

## 6.1 Roles
- homeowner-owner
- homeowner-member
- contractor
- seeker
- admin/support (internal)

## 6.2 Authorization principles
- home data is private by default
- access is explicit and scoped by home
- mutations require CSRF + role checks + ownership checks
- disclosure views are generated from safe, redacted projections

## 6.3 Auditability
Record actor, action, timestamp, before/after references for:
- job submission events
- review decisions
- published disclosure snapshots

---

## 7) Architecture Blueprint

## 7.1 Keep now
- Controllers -> Services -> Repositories layering
- thin public action delegates (already standardized)

## 7.2 Adopt next
- front-controller route map (incremental, backward-compatible)
- middleware-like policies:
  - requireLogin
  - requireHome
  - requireRole
  - requireCsrf
- response contracts:
  - standard JSON envelope for API endpoints
- structured logging with request correlation IDs

## 7.3 Suggested module boundaries
- `Domain/Home`
- `Domain/Items`
- `Domain/Tasks`
- `Domain/History`
- `Domain/ContractorJobs`
- `Domain/Disclosures`
- `Domain/Seekers`

---

## 8) Execution Strategy (Carry Forward)

## Phase 1: Foundation Hardening (0-6 weeks)
- stabilize security/config/observability
- formalize route map entrypoint while preserving existing URLs
- strengthen tests around critical homeowner flows

### Exit criteria
- no hardcoded secrets
- all mutating endpoints behind uniform guards
- baseline integration tests for item/task/history lifecycle

## Phase 2: Contractor Portal MVP (6-14 weeks)
- contractor profiles and invitations
- job assignment + submission + review pipeline
- approved submissions write into homeowner history

### Exit criteria
- end-to-end contractor->homeowner approval flow works
- immutable submission/review audit trail

## Phase 3: Home Seeker Portal MVP (14-24 weeks)
- disclosure profile + publish controls
- seeker-facing timeline/system cards
- owner-seeker inquiry messaging

### Exit criteria
- homeowner can publish/unpublish listing-safe profile
- seeker can evaluate maintenance evidence and contact owner

## Phase 4: Trust/Value Intelligence (24+ weeks)
- confidence score and resale evidence package
- contractor quality signals and repeat-work analytics
- optional pricing/valuation assist models

---

## 9) KPIs and Success Metrics

### Homeowner KPIs
- monthly active homeowners
- task completion rate
- overdue task reduction
- % of major systems with documented history

### Contractor KPIs
- invited contractor activation rate
- submission approval rate
- turnaround time from submit to review
- repeat job ratio per contractor

### Seeker KPIs
- disclosure profile views
- inquiry conversion rate
- time-to-confidence (session depth before inquiry)

### Platform Trust KPIs
- % history entries with verification artifacts
- dispute/rejection rate by submission type
- user-reported confidence improvement

---

## 10) Risks and Mitigations
- **Privacy leakage risk** -> strict disclosure projection layer and redaction rules
- **Fraud/low-quality submissions** -> approval workflow + anomaly checks + proof requirements
- **Portal complexity overload** -> phased rollout, role-specific UI, progressive disclosure
- **Operational burden** -> standardized moderation/admin tooling early

---

## 11) Redesign Priorities (Product + UX)
1. Unify homeowner item detail into a single timeline-centric workspace
2. Add contractor submission inbox in homeowner portal
3. Introduce disclosure publishing wizard for seekers
4. Add cross-portal communication and notification center

---

## 12) Immediate Next Build Slice (Recommended)
**First vertical slice after this blueprint:**
1) Add contractor role + contractor profile
2) Add `service_jobs` + `job_submissions` tables
3) Add homeowner review queue UI
4) On approval, append canonical history entry (with proof links)

This gives the highest strategic leverage and unlocks seeker value later without rework.

---

## 13) Architectural Position on Delegates vs Direct Calls
Keep current thin delegates until front-controller routing is introduced.
- they preserve separation of concerns today
- they allow incremental migration safely
- they avoid mixing page rendering and command execution

Route map migration should be additive, not big-bang.
