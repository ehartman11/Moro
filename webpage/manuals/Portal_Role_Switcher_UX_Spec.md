# Portal Role-Switcher UX Spec (v1)

## 1) Objective

Design a scalable navigation model for multi-role users so the app does not rely on one increasingly cluttered shared navbar.

Primary goals:

- keep global navigation simple,
- make role context explicit,
- show only relevant links for the current portal,
- support users who can act as homeowner, contractor, and seeker.

## 2) UX Decision

Adopt a **two-level navigation shell**:

1. **Global Header** (always visible)
2. **Portal Navigation** (changes by active role)

This replaces the long flat shared navbar as the primary interaction model.

## 3) Information Architecture

### 3.1 Global Header (Level 1)

Always visible for authenticated users:

- Brand/logo (Home)
- Active Home selector/link
- Role switcher (MyHome / Contracting / Searching)
- User menu (Profile, Homes, Logout)

Principles:

- no feature-deep links in global header,
- keep global actions cross-portal only,
- role switch is visible and one-click.

### 3.2 Portal Navigation (Level 2)

Rendered based on active role context.

#### MyHome portal

- Items
- MRCs
- Feedback
- Countdown

#### Contracting portal

- Contractor Profile
- My Jobs
- Submissions
- Owner Job Inbox (when owner capability exists in active home)

#### Searching portal (MVP placeholder)

- Overview
- Saved Homes (placeholder)
- Inquiries (placeholder)

Notes:

- Searching links may initially point to placeholder pages until full seeker endpoints are scaffolded.

## 4) Role Switcher UX

## 4.1 Placement

- Primary: visible control in global header.
- Secondary: duplicate in user dropdown for convenience.

## 4.2 Behavior

When user selects a role:

1. Persist selected role in session (e.g., `active_portal_role`).
2. Redirect to that portal default landing page.
3. Render portal-specific nav.
4. Show contextual message if role lacks capability in current home/account.

## 4.3 Capability checks

- `MyHome` role: authenticated user with active home context.
- `Contracting` role: contractor capability (contractor profile presence).
- `Searching` role: seeker capability (home role-based; currently guard scaffolding exists).

If capability is missing:

- keep current portal unchanged,
- show non-blocking error/notice,
- provide next action (e.g., complete contractor profile).

## 5) Navigation State Model

## 5.1 Session state

- `active_home_id` (existing)
- `active_portal_role` (new)

## 5.2 Defaults

On login:

1. If previous valid role exists in session, use it.
2. Else default to `myhome`.

On home switch:

- re-validate active role against new home/account context,
- if invalid, fallback to first valid role in priority order:
  1. myhome
  2. contracting
  3. searching

## 6) Guard + Routing Contract

Portal switch and portal entry pages should use centralized guard helpers.

- Owner/home guard patterns remain unchanged.
- Contractor uses contractor profile guard.
- Seeker uses seeker role guard (`seeker_role_required`).

Portal switching must never bypass endpoint-level authorization checks.

## 7) UI Content and Labels

Use consistent role labels:

- `MyHome`
- `Contracting`
- `Searching`

Error labels:

- `contractor_profile_required` -> "Complete your contractor profile to access Contracting."
- `seeker_role_required` -> "Your current home context does not include seeker access."

## 8) Responsive Behavior

Desktop:

- role switcher inline in header,
- portal nav as horizontal links under header.

Mobile:

- role switcher remains visible in compact menu,
- portal nav collapses into a portal-specific dropdown/menu.

## 9) Rollout Plan

## Phase 1 (Immediate)

- Add role switcher control and session state.
- Split current nav into global + portal sections.
- Keep existing page URLs; only change nav structure.

## Phase 2

- Move current contractor links fully under Contracting nav.
- Add Searching placeholders tied to seeker guard.

## Phase 3

- Introduce seeker portal endpoints and wire placeholders.
- Retire any leftover redundant links from global header.

## 10) Acceptance Criteria

1. User can switch between MyHome and Contracting without logging out.
2. Navigation links shown are scoped to active portal.
3. Missing capability blocks portal access with clear message, no hard failure.
4. Existing direct URLs remain functional and still enforced by endpoint guards.
5. Global header remains stable while portal nav changes by selected role.

## 11) Non-Goals (v1)

- No redesign of page internals/content layouts.
- No permission model rewrite.
- No new seeker business workflows in this spec.

## 12) Implementation Mapping (Next Build Step)

Likely first files for implementation:

- `nav_bar.php` (global + portal shell rendering)
- `public/actions.php` (role switch action route)
- `src/Http/Controllers/RequestContext.php` (active portal resolution helper)
- `src/core/Auth.php` (portal capability checks reuse)
- small CSS updates in `public/styling/nav.css`

This spec intentionally separates UX shell from deeper seeker feature development.
