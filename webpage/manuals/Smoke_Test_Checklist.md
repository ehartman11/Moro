# Smoke Test Checklist

Use this checklist after structural/routing changes (especially `public/actions.php` updates).

## Test Setup

- Launch server from VS Code using `.vscode/launch.json` (recommended profile).
- Confirm app URL base works:
  - `http://localhost:8000/webpage/public/index.php`
- Test with a valid owner account and a valid contractor account.

---

## 1) Public + Auth

- [ ] Open Home page (`/webpage/public/index.php`) and confirm CSS loads.
- [ ] Click nav **Login** and confirm Sign In page appears (not Home content).
- [ ] Open Register page and confirm form renders.
- [ ] Register test account (or validate duplicate email error).
- [ ] Login with valid credentials and confirm redirect to Homes page.

---

## 2) Homes Context

- [ ] On Homes page, set active home.
- [ ] Confirm active home context allows navigating to Items/MRCs/Feedback.
- [ ] Confirm unauthorized home selection is blocked.

---

## 3) Items Flows (Dispatcher)

- [ ] Add item from Details tab.
- [ ] Edit existing item and save.
- [ ] Delete item (owner only) and verify redirect + flash.
- [ ] Add task in Maintenance tab.
- [ ] Complete task and verify schedule updates.
- [ ] Add history entry (with and without photo upload).
- [ ] Edit history entry and delete history entry.
- [ ] Download/view manual opens expected content.

Routes behind these should resolve via `public/actions.php?action=items.*`.

---

## 4) Maintenance Cards + Feedback

- [ ] Maintenance cards load for selected item.
- [ ] Task card fetch works (no 404/JSON error).
- [ ] Upload PDF from maintenance cards works.
- [ ] Publish flow works for owner.
- [ ] Submit feedback from maintenance feedback form works.
- [ ] Feedback dashboard loads and status filter updates correctly.
- [ ] Save feedback notes and update feedback status.

Routes behind these should resolve via `public/actions.php?action=maintenance.*` and `feedback.*`.

---

## 5) Contractor Portal MVP

### Owner-side
- [ ] Save contractor profile.
- [ ] Create service job from contractor index owner section.
- [ ] Owner Job Inbox lists jobs; badge filters work.
- [ ] Open submission review page from a job.
- [ ] Review decision updates submission and parent job state:
  - approve -> job `completed`
  - needs_changes -> job `in_progress`
  - reject -> job `cancelled`

### Contractor-side
- [ ] My Jobs page lists assigned jobs in active home.
- [ ] Submit work with summary only.
- [ ] Submit work with media upload (image/PDF).
- [ ] Homeowner review page shows media links/thumbnails.
- [ ] Media open via `public/contractor/media_view.php`.
- [ ] Contractor actions block users without contractor profile (`contractor_profile_required`).

### Multi-role account behavior
- [ ] Same user can act as owner and contractor in appropriate contexts.
- [ ] Owner-only actions still require owner permission for active home.
- [ ] Contractor actions require contractor profile regardless of owner role.
- [ ] Seeker endpoints (when enabled) require seeker home role (`seeker_role_required` otherwise).

Routes behind these should resolve via `public/actions.php?action=contractor.*`.

---

## 6) Error + Security Checks

- [ ] Missing/invalid CSRF on POST is blocked.
- [ ] Viewer role cannot access owner-only actions.
- [ ] Invalid action key in dispatcher returns JSON 404:
  - `{ "ok": false, "error": "unknown_action" }`
- [ ] Missing `action` returns HTTP 400 `Missing action.`
- [ ] Invalid direct media/content IDs return controlled error/404.

---

## 7) Quick Regression Checks

- [ ] No references remain to deleted delegate paths under `public/*/actions/*.php`.
- [ ] No missing file errors in server logs for old delegate routes.
- [ ] Core nav links (Home, Login, Homes, Items, MRCs, Feedback, Contractor) load expected pages.

---

## Suggested Test Data

- Home with at least 1 item and 1 maintenance task.
- One contractor user and one owner user linked to same home context.
- One open service job with at least one submitted job submission.
- One submission including image and one including PDF media.
