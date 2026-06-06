# Portal Role-Switcher: Phase 1 Implementation Spec

## 1) Scope

Phase 1 implements navigation-shell foundations only.

In scope:

- add active portal role session sta3 e,
- add role switch action,
- split navbar into global header + portal nav,
- keep all existing page URLs and endpoint guards intact,
- provide clear fallback messaging when role is unavailable.

Out of scope:

- seeker feature workflows/endpoints,
- redesigning page body layouts,
- permission model rewria3.

## 2) Functional Outcomes

After Phase 1:

1. Authenticated users can switch active portal role (`myhome`, `contracting`, `searching`).
2. Navbar shows links relevant to selected portal.
3. Invalid role switches do not break navigation; user is redirected with an error code.
4. Direct URL access still works and remains protected by existing endpoint-level guards.

## 3) State and Contracts

## 3.1 Session keys

- Existing: `active_home_id`
- New: `active_portal_role`

Allowed values:

- `myhome`
- `contracting`
- `searching`

Default behavior:

- If unset or invalid, resolve to first available role by priority:
  1. `myhome`
  2. `contracting`
  3. `searching`

## 3.2 Error codes

- `portal_role_invalid`
- `portal_role_unavailable`
- existing capability errors remain unchanged:
  - `contractor_profile_required`
  - `seeker_role_required`

## 4) UX Flow

## 4.1 Role switch

Trigger: user selects role from header switcher.

Flow:

1. POST to dispatcher action `nav.switch_portal` with CSRF token and selected role.
2. Server validates role and capability for current user/home context.
3. On success:
   - set `$_SESSION['active_portal_role']`.
   - redirect to portal default page.
4. On failure:
   - keep previous role.
   - redirect to `return_to` with `err=portal_role_unavailable` (or `portal_role_invalid`).

## 4.2 Portal default landing pages

- `myhome` -> `/public/items/index.php`
- `contracting` -> `/public/contractor/index.php`
- `searching` -> `/public/index.php` (temporary until seeker pages ship)

## 5) File-Level Implementation Plan

## 5.1 `src/core/Auth.php`

Add portal capability helpers:

- `canUsePortalRole(PDO $pdo, int $userId, int $homeId, string $role): bool`
- `resolveDefaultPortalRole(PDO $pdo, int $userId, int $homeId): string`

Rules:

- `myhome`: true if logged in + active home exists.
- `contracting`: requires contractor profile capability.
- `searching`: requires seeker role capability.

## 5.2 `src/Http/Controllers/RequestContext.php`

Add nav/portal context helper:

- `portalContext(array $input, string $defaultReturnTo): array`

Returns:

- `pdo`, `userId`, `homeId`, `role` (home role), `returnTo`, `activePortalRole`

Behavior:

- reads `$_SESSION['active_portal_role']`,
- validates/falls back using `Auth::resolveDefaultPortalRole(...)`,
- writes corrected value back to session.

## 5.3 New controller: `src/Http/Controllers/Nav/SwitchPortalController.php`

Method:

- `handle(): never`

Responsibilities:

- require POST,
- require login + active home,
- require CSRF,
- validate requested role,
- capability-check role using `Auth::canUsePortalRole(...)`,
- set session role and redirect to default portal URL.

## 5.4 `public/actions.php`

Add route:

- `nav.switch_portal` -> `Moro\Http\Controllers\Nav\SwitchPortalController::handle`

No changes to existing route keys.

## 5.5 `nav_bar.php`

Refactor rendering into two sections:

1. Global header:
   - logo,
   - role switch form,
   - user dropdown.
2. Portal nav:
   - conditional links by `active_portal_role`.

Portal link sets:

- `myhome`: Countdown, Homes, Items, MRCs, Feedback
- `contracting`: Contractor Portal, My Jobs, Submissions, Owner Inbox (if home role owner)
- `searching`: single placeholder link to Home until seeker pages exist

## 5.6 `public/styling/nav.css`

Minimal additions only:

- role switch control styles,
- spacing/layout for two-level nav,
- responsive collapse for portal link row.

No new design system tokens.

## 6) Security and Guarding

Requirements:

- role switch endpoint must be CSRF-protected,
- role switch cannot elevate permissions,
- endpoint-level guards remain source of truth,
- portal role in session is advisory for navigation, not authorization.

## 7) Backward Compatibility

- Existing pages and direct links remain valid.
- Existing dispatcher actions remain unchanged.
- If role-switch UI fails, nav can still render fallback `myhome` links.

## 8) QA Checklist (Phase 1)

1. Login user with owner+contractor capabilities:
   - switch between `myhome` and `contracting` works.
2. User without contractor profile:
   - selecting `contracting` returns `portal_role_unavailable`.
3. User without seeker role:
   - selecting `searching` returns `portal_role_unavailable`.
4. Session persistence:
   - refresh and page changes keep selected portal role.
5. Home switch behavior:
   - invalid role for new home falls back gracefully.
6. Regression:
   - existing routes still reachable and guarded correctly.

## 9) Rollback Plan

If issues occur:

1. remove `nav.switch_portal` route from dispatcher,
2. revert `nav_bar.php` to current flat nav,
3. ignore `active_portal_role` session key.

No database changes are involved, so rollback is code-only.

## 10) Definition of Done

- all listed files implemented,
- no diagnostics errors on changed files,
- manual QA checklist items pass,
- no regressions in contractor/homeowner workflows.