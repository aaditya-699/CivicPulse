# CivicPulse

Municipal Infrastructure Tracking & Analytics Portal — a BCA 4th Semester
Project I submission (TU, CACS259).

Citizens report infrastructure defects (potholes, broken streetlights,
leaking pipes, etc.), municipal staff verify and resolve them, and admins
oversee the whole queue, manage staff accounts, and review reports.

## Tech stack

PHP 8 (mysqli, no framework), MySQL/MariaDB, HTML5/CSS3, vanilla JavaScript.

## Setup (XAMPP / local PHP dev server)

1. Copy this folder into `htdocs/civicpulse` (XAMPP) or serve it directly
   with `php -S localhost:8000` from inside the folder.
2. Import the schema: open phpMyAdmin (or run
   `mysql -u root -p < database/civicpulse_database.sql`) and run
   `database/civicpulse_database.sql`. This drops and recreates
   `civicpulse_db` from scratch, including sample data.
3. Check `config.php` — `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS` should match
   your local MySQL setup (defaults assume XAMPP: `localhost:3307`, user
   `root`, no password).
4. Make sure `uploads/` and `logs/` are writable by the web server
   (`chmod 755` is usually enough on a local dev box).
5. Visit `index.php` in your browser.

## Demo accounts

All seeded accounts use the password **`Password123`**.

| Role    | Email                        |
|---------|-------------------------------|
| Citizen | citizen1@civicpulse.local     |
| Citizen | citizen2@civicpulse.local     |
| Staff   | staff1@civicpulse.local       |
| Staff   | staff2@civicpulse.local       |
| Admin   | admin@civicpulse.local        |

## How a complaint moves through the system

```
reported --(staff verifies)--> verified --(admin assigns to staff)--> in_progress
                                                                            |
                                                            (assigned staff resolves)
                                                                            v
                                                     closed <--(admin closes)-- resolved

reported/verified --(staff or admin)--> rejected
```

Every transition above is enforced twice: once in the UI (only the relevant
button is shown) and again inside `updateComplaintStatus()` /
`assignComplaintToStaff()` in `complaints.php`, so the workflow can't be
bypassed by posting to the page directly.

## Project structure

```
civicpulse/
├── config.php              Database connection wrapper + app constants
├── functions.php           Helpers: sanitizing, CSRF, flash messages, logging, formatting
├── auth.php                Session handling, login/registration, role checks, login throttling
├── complaints.php          Complaint + task CRUD, statistics, attachments
├── header.php / footer.php Shared page chrome (nav bar built from the user's role)
├── assets/css/style.css    One shared stylesheet for the whole app
├── database/
│   └── civicpulse_database.sql
├── uploads/                 Complaint photo/PDF attachments (.htaccess blocks script execution)
├── logs/                    Error log files (rotated daily)
│
├── index.php                Redirects to login/dashboard
├── login.php                Login + citizen self-registration
├── logout.php
├── unauthorized.php         Shown when a role tries a page it can't access
├── dashboard.php            Role-specific landing page
├── report_complaint.php     Citizen: submit a new complaint (with photo)
├── my_complaints.php        Citizen: their own complaint history
├── complaintdetail.php      Shared detail/action page, access controlled per role
├── assignedcomplaints.php   Staff: complaints assigned to them
├── managecomplaints.php     Staff/Admin: full complaint queue
├── manage_users.php         Admin: create/delete staff & admin accounts
├── reports.php              Admin: aggregate statistics
└── profile.php / settings.php   Every role: profile info, password change
```

## Notes for graders / reviewers

See `AUDIT.md` for the full list of bugs found in the original codebase and
how each was fixed, plus what's intentionally out of scope for this
submission (e.g. email notifications — the config has SMTP settings but no
mailer is wired up, since it can't be exercised without a real mail server).
