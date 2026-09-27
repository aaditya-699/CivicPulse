# CivicPulse — Code Audit Report

Prepared for: BCA 4th Semester Project I (CACS259)
Scope: Full audit of the submitted codebase (19 PHP files + SQL schema), followed by a complete, functionally-tested rebuild.

**Testing note:** this isn't a desk review. I stood up a real MySQL instance
and PHP server in a sandbox, imported the schema, and drove the app through
curl for every role and every state transition — login, complaint
submission with a real file upload, the full verify → assign → resolve →
close workflow, CSRF rejection, IDOR blocking, and the account-creation bug
fix. Everything described as "fixed" below was actually exercised, not just
reasoned about.

---

## 1. Executive Summary

The original submission had the right *shape* for a project like this —
clear roles, a sensible table structure, prepared statements used
consistently for SQL — but it had several bugs severe enough that core
features didn't work at all when actually run against the schema:

- **Admin could not create staff/admin accounts** (wrong column name in the INSERT).
- **The entire "staff assigned complaints" feature was broken** (query referenced a column that doesn't exist).
- **No demo account could log in** (seed passwords weren't real hashes).
- **Any citizen could read any other citizen's complaint** (no ownership check — an IDOR vulnerability).
- **Photo attachments were completely non-functional** (the form had no file input despite the CSS/JS expecting one).
- Several pages existed only as dead links (`my_complaints.php`), or were unreachable from the UI (`reports.php`, `setting.php`, `unauthorized.php`).
- No CSRF protection anywhere; state-changing deletes used GET links.

None of these are exotic — they're the kind of thing that shows up when a
feature is coded against an assumed schema instead of the actual one, and
then never run end-to-end. The fix wasn't a redesign; it was closing the
gap between the code and the database, adding the one workflow feature
that was missing (staff assignment, backed by the already-designed-but-unused
`tasks` table), and adding the security basics a TU evaluator will expect
to see (CSRF tokens, output escaping, ownership checks, throttled logins).

### Audit checklist

| Area | Before | After |
|---|---|---|
| Account creation (admin → staff/admin) | Fatal SQL error | Working, tested |
| Staff "assigned to me" view | Fatal SQL error | Working, tested |
| Demo/seed login | Impossible (bad hashes) | Working, tested |
| Complaint access control | IDOR — any complaint viewable by any citizen | Ownership-checked per role |
| File attachments | UI referenced a field that didn't exist | Working upload + MIME-sniffed validation, tested |
| Staff assignment workflow | No such feature existed | Built on the existing `tasks` table, full lifecycle tested |
| CSRF protection | None | Token on every state-changing form, verified server-side |
| Password change | Admin-only page, other roles locked out | Available to every role |
| Status values | `'solved'` used but not in DB's ENUM | Workflow matches the ENUM exactly, transitions enforced server-side |
| Dev/debug files | `generate_hash.php`, `test_connection.php` publicly reachable | Removed |
| CSS | ~2,000 duplicated lines across pages | One shared stylesheet |
| Missing DB table | `complaint_notes` referenced in code, never created | Added to schema |

---

## 2. Critical Bugs Found (would have failed a live demo)

1. **`manage_users.php` — wrong column name.**
   `INSERT INTO users (full_name, email, password, user_role)` — the table's
   column is `password_hash`. Every attempt to create a staff or admin
   account failed with a SQL error.

2. **`assignedcomplaints.php` / `getStaffComplaintStatistics()` — wrong table.**
   Both queried `complaints.assigned_to`. That column doesn't exist —
   assignment lives on the `tasks` table. The staff "my assigned complaints"
   page and the staff dashboard stats were both fatal on every load.

3. **Seed data passwords weren't hashes.**
   `civicpulse_database.sql` inserted `password_hash = '123456789'` (a plain
   string) for every demo user. `password_verify()` can never match a plain
   string against a bcrypt comparison, so none of the seeded accounts —
   including the only admin account — could log in on a fresh install.

4. **IDOR on `complaintdetail.php`.**
   The only access check was `requireLogin()`. A logged-in citizen could
   view *any* complaint — including another citizen's — by changing the
   `?id=` parameter. Fixed with `canViewComplaint()`, enforced per role.

5. **Status workflow used a value not in the schema.**
   The status ENUM is `reported, verified, in_progress, resolved, closed,
   rejected`. The application code wrote `'solved'` in two places. MySQL
   either rejects this or (in non-strict mode) silently coerces it to an
   empty string, corrupting the row. Rebuilt the whole lifecycle around the
   schema's actual values (see README for the diagram) and enforce legal
   transitions **server-side**, not just by hiding buttons in the UI —
   tested that a non-assigned staff member's direct POST is rejected even
   when they know the field names.

6. **File attachments were non-functional.**
   `report_complaint.php` had CSS and JavaScript referencing an
   `#attachment` file input and called `uploadComplaintAttachment()` — but
   the actual `<form>` had no `<input type="file">` element, and no
   `enctype="multipart/form-data"` on the form tag, so no file could ever
   be sent. Fixed, and hardened: uploads are validated by real MIME
   sniffing (`finfo`), not just the file extension — verified a
   `.php`-content file renamed to `.jpg` is rejected and never touches disk.

7. **`complaint_notes` table used but never created.**
   The original `addComplaintFeedback()` function already referenced this
   table; it just didn't exist in the schema. Added it, and built the
   "Notes & Updates" panel on the complaint detail page around it.

---

## 3. Security Issues Fixed

| Issue | Fix |
|---|---|
| No CSRF protection | Session-bound token (`csrfField()` / `csrfVerify()`) on every POST form; forged tokens verified to 403 |
| Delete via GET link | Converted to POST forms with CSRF tokens (GET must not have side effects, and GET links are trivially CSRF'd via an `<img>` tag) |
| No login throttling despite `MAX_LOGIN_ATTEMPTS` being defined | Implemented using the existing `activity_log` table — 5 failed attempts locks an email out for 15 minutes |
| File upload trusted extension/client MIME type | Real content sniffing via `finfo`, random filenames, `.htaccess` in `uploads/` denies script execution as defense-in-depth |
| `sanitizeInput()` applied `htmlspecialchars()` at input time | Moved escaping to output time only (`e()` helper) — encoding at input time causes double-escaping once data round-trips through the DB and back through a second `htmlspecialchars()` at render |
| No session fixation protection | `session_regenerate_id(true)` on login |
| Dev/debug files reachable in production | `generate_hash.php` (exposed a working bcrypt hash) and `test_connection.php` (exposed DB name) removed |
| `setting.php` restricted to admin only | Opened to all roles — every account can now change its own password |

---

## 4. Missing Features Added

- **Staff assignment workflow.** The `tasks` table existed in the schema and had sample data, but nothing in the codebase ever wrote to it. Built the full loop: staff verifies a new report → admin assigns it to a specific staff member (with an optional due date) → assigned staff marks it resolved → admin closes it. Enforced both in the UI and server-side.
- **`my_complaints.php`** — a real page behind the two links that already pointed to it, with status filtering and pagination.
- **Complaint notes/timeline** — citizens, staff, and admins can leave notes on a complaint; shown in a simple timeline.
- **Login throttling**, using constants that were already defined but unused.
- **`unauthorized.php`** — the page `requireRole()` already redirected to, but which never existed.

## 5. Out of scope (flagged, not built)

- **Email notifications.** `config.php` has SMTP-shaped settings but no mailer was ever wired up in the original code, and I can't test an actual send without a real mail server. Left out rather than adding untested code.
- **Category management UI.** Categories are seeded and used correctly everywhere; an admin CRUD screen for them wasn't part of the original scope and isn't required by the listed modules, so I didn't add scope beyond what was asked.

---

## 6. File-by-File Changes

| File | Change | Why |
|---|---|---|
| `config.php` | Rewritten — connection wrapper + constants only | Split from helper functions for a clean single responsibility |
| `functions.php` | **New** | sanitizing, CSRF, flash messages, logging, formatting — previously mixed into config.php |
| `auth.php` | Rewritten | Session fixation fix, login throttling, cleaner role checks |
| `complaints.php` | Rewritten | Fixed `assigned_to` bug, fixed `'solved'` status bug, added task-assignment functions, added notes, fixed staff/citizen-scoped stats |
| `header.php` / `footer.php` | **New** | Shared nav/layout, replacing per-page duplicated markup |
| `assets/css/style.css` | **New** | Replaces ~2,000 duplicated lines of inline `<style>` blocks |
| `index.php` | Simplified | Just a role-aware redirect |
| `login.php` | Rewritten | Added CSRF, kept the tab UI |
| `logout.php` | Unchanged in behavior | — |
| `unauthorized.php` | **New** | Was a dead redirect target |
| `dashboard.php` | Rewritten | Fixed staff seeing site-wide stats instead of their own |
| `report_complaint.php` | Rewritten | Fixed missing file input / enctype / upload call |
| `my_complaints.php` | **New** | Was a dead link |
| `complaintdetail.php` | Rewritten | Fixed IDOR, fixed status workflow, added assignment UI, added notes |
| `assignedcomplaints.php` | Rewritten | Fixed the fatal `assigned_to` column bug |
| `managecomplaints.php` | Rewritten | Added pagination UI, fixed GET-delete CSRF hole |
| `manage_users.php` | Rewritten | Fixed the `password` vs `password_hash` bug, added CSRF |
| `profile.php` | Rewritten | Minor cleanup, links to settings |
| `reports.php` | Rewritten | Now reachable from nav |
| `setting.php` → `settings.php` | Rewritten, renamed | Opened to all roles (was admin-only) |
| `database/civicpulse_database.sql` | Rewritten | Added `complaint_notes` table, real bcrypt seed hashes |
| `generate_hash.php`, `test_connection.php` | **Deleted** | Dev/debug leftovers exposing sensitive info |
| `uploads/.htaccess` | **New** | Defense-in-depth against executing uploaded files |

---

## 7. Demo credentials

All seeded accounts use the password **`Password123`**. See `README.md` for
the full list and setup steps.
