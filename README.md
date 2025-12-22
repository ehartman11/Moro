Moro — Home Maintenance & Asset Management
Moro is a web-based home maintenance and asset management application designed to help users track items, schedule recurring maintenance, record service history, and visualize upcoming work without clutter or complexity.
The system is intentionally data-centric, permission-aware, and deterministic, favoring clear workflows and long-term maintainability over “magic” behavior.

Core Concepts
Homes
•	A Home is the top-level container for all data.
•	Users may belong to multiple homes.
•	One home is always marked as the active home via session state.
•	All reads/writes are scoped to the active home.

Items
•	Items represent assets that require upkeep:
o	homes
o	vehicles
o	appliances
o	tools
o	outdoor equipment
•	Each item belongs to exactly one home.
•	Items act as the parent for maintenance tasks and history.

Maintenance Tasks
•	Tasks define what needs to be done and how often.
•	Each task has:
o	frequency value + unit
o	priority
o	optional description
•	Each task has exactly one schedule row (task_schedule) representing the next due date.
•	Tasks are advanced in place when completed — no duplication, no “completed” flags.

Maintenance History
•	When a task is completed, a history record is created.
•	History records store:
o	completion date
o	optional notes
o	optional cost
o	optional photo references
•	Completing a task automatically advances its next due date.

Countdown / Tickler
•	The countdown view aggregates scheduled tasks by date.
•	Uses server-synced time to avoid client drift.
•	Urgency is visually encoded:
o	green → safe
o	yellow → approaching
o	red → imminent
o	pulse → overdue

Authentication & Authorization
Sessions
•	PHP sessions are used for authentication.
•	Key session values:
o	user_id
o	user_name
o	user_role
o	active_home_id

Home Permissions
•	Access is controlled via home_permissions.
•	Roles:
o	owner — full CRUD access
o	viewer — read-only
•	Role is resolved per active home, not globally.

Authorization Enforcement
•	All mutating actions pass through _auth.php.
•	Owner-only actions call require_owner() before execution.
•	UI visibility mirrors backend permissions (never relied on alone).

Design Principles
Deterministic Data Model
•	One schedule row per task.
•	No implicit state.
•	History is append-only.

Clear Ownership Boundaries
•	Home → Item → Task → History
•	Every query is scoped by home ownership.

Explicit Error Handling
•	All actions redirect with stable error codes.
•	UI maps error codes → user-friendly messages.
•	No silent failures.

Minimal Magic
•	No ORMs.
•	No background jobs.
•	No hidden recalculations.
Everything happens when the user acts.

Database Notes
•	MySQL with utf8mb4 encoding.
•	PDO with:
o	exception mode
o	associative fetch
•	Foreign keys are expected to enforce integrity.
•	Cascades should be defined for:
o	items → tasks
o	tasks → schedules
o	tasks → history

Security Considerations
•	All user input is validated server-side.
•	All output is escaped (htmlspecialchars).
•	All mutating actions:
o	require POST
o	validate ownership
o	enforce role checks
•	No client-side authorization assumptions.

Current Limitations / Known Tradeoffs
•	Photo uploads are currently stored as file paths (not binary).
•	No background reminders (email/push).
•	No audit log beyond maintenance history.
•	No soft deletes (by design).

Intended Evolution
•	File uploads with validation
•	Exportable maintenance history
•	Reminder delivery (email / notifications)
•	Role expansion (contractors, read-only auditors)
•	Mobile-first UI pass

Philosophy
Moro is built for people who want control without chaos.
No dashboards full of noise.
No over-abstracted logic.
No surprise automation.
Just:
•	clear data
•	predictable behavior
•	long-term trust in what the system is doing.
