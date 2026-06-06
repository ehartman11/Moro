# Role Capability Matrix (Multi-Role Accounts)

This project supports **multi-role users on a single account**. A user may operate as homeowner, contractor, and seeker depending on current action context.

## Core Principle

Authorization is evaluated by:

1. authenticated user (`user_id`),
2. active home context (`active_home_id` where applicable),
3. action capability (owner permission, contractor profile, etc.).

A single global role on `users` is not treated as the source of truth for all actions.

## Current Capability Rules

### Homeowner capabilities (home-scoped)

- Source: `home_permissions.role = owner` for active home.
- Guard path:
  - `Moro\Http\Controllers\RequestContext::ownerContext(...)`
  - `Moro\Core\Auth::requireOwner(...)`

Used for:

- creating service jobs,
- reviewing contractor submissions,
- owner-only item/admin actions.

### Seeker capabilities (home-scoped, role-based)

- Source: `home_permissions.role = seeker` for active home context.
- Guard path:
  - `Moro\Core\Auth::requireSeeker(...)`
  - `Moro\Http\Controllers\RequestContext::seekerContext(...)`

Status:

- Guard scaffolding is in place.
- Seeker action endpoints are not yet scaffolded; they should use `RequestContext::seekerContext(...)` when added.

### Contractor capabilities (account-scoped, optionally home-filtered by data)

- Source: presence of row in `contractor_profiles` for current user.
- Guard path:
  - `Moro\Core\Auth::hasContractorProfile(...)`
  - `Moro\Core\Auth::requireContractorProfile(...)`
  - `Moro\Http\Controllers\RequestContext::contractorContext(...)`

Used for:

- contractor submit-work action,
- contractor jobs JSON endpoint.

Data ownership checks still enforce home/job boundaries in service/repository layers.

## Why this model

- avoids forcing multiple accounts for one person,
- keeps UX simple while preserving authorization boundaries,
- supports future seeker capabilities without redesigning identity.

## Implementation Guidance

When adding a new action:

1. decide if capability is owner-scoped or contractor-scoped,
2. use `RequestContext` helper for that capability,
3. keep fine-grained ownership validation in service/repository methods,
4. return stable error keys (e.g., `unauthorized`, `contractor_profile_required`) for UI handling.

For seeker endpoints specifically:

- use `RequestContext::seekerContext(...)`,
- map `seeker_role_required` to a user-facing access message,
- keep record-level ownership checks in service/repository code.
