# ICOTAS

[**Intelligent Class Observation and Teaching Analytics System**](https://icotas.kesug.com)
ACLC College Tacloban City

ICOTAS is a native PHP 8 academic evaluation and teaching-analytics platform. It combines anonymous QR-based student evaluations, instructor-private analytics, rule-based sentiment and theme analysis, transparent trend indicators, class observations, ISO/IEC 25010 user acceptance testing (UAT), institutional reports, and a Dean/Academic Head administration console.

> **Implementation status.** The application is complete and has been validated locally against an isolated test database. It has **not** been deployed to or tested on InfinityFree. Compatibility with that host is a deployment target, not a verified claim. See `documentation/testing.md` for exact evidence.

---

## Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.0+ (target PHP 8.4; verified on PHP 8.0.30) |
| Database | MySQL 5.7+ / MariaDB 10.3+ via PDO, `utf8mb4` |
| Web server | Apache with `mod_rewrite` (InfinityFree or XAMPP) |
| UI | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, custom CSS |
| Theme | Blue primary (`#144a9c`), red accent (`#c62828`), white surfaces; semantic success/warning colors retained |
| Charts | Chart.js 4.4.4 (institutional and instructor dashboards) |
| QR | qrcodejs 1.0.0 for display, jsQR 1.4.0 for camera scanning |
| PDF | FPDF 1.9 with bundled JSON font metrics |
| Dependencies | None installed at runtime; all assets are vendored |

No Node, Python, PostgreSQL, Redis, WebSockets, cron, or external AI service is required. Reports are CSV or PDF. There is no email delivery; UAT invitations are copyable links.

---

## Repository layout

```
index.php                 Front controller
install.php               Browser installer (delete after installation)
.htaccess                 Rewrite rules and source-directory protection
app/
  Core/                   Router, request, database, auth, CSRF, sessions, security
  Controllers/            HTTP controllers for dean, instructor, student, UAT
  Models/                 Focused data-access models
  Services/               Analytics, AI, prediction, QR, reports, UAT, demo data
  Views/                  Server-rendered PHP templates
  Libraries/              FPDF 1.9 and font metrics
assets/                   Vendored CSS/JS and application styles
config/                   Application config, local sample, secrets (ignored)
database/schema.sql       44-table schema and seed settings
database/migrations/      Upgrade SQL for existing installations
storage/                  Logs, cache, reports, installer lock (not web-readable)
documentation/            Architecture, security, testing, deployment, research notes
tests/                    Dependency-free automated tests (development only)
tools/                    CLI and HTTP test utilities (development only)
```

`app/`, `config/`, `database/`, `documentation/`, `storage/`, `tests/`, and `tools/` are denied by the root `.htaccess`. Every directory also has its own deny file as a second layer.

---

## Clone, install, and start

### 1. Requirements

| Component | Requirement |
|---|---|
| PHP | 8.0 or newer (8.4 recommended). Verified on 8.0.30 and 8.5.6 |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `json` |
| Database | MySQL 5.7+ or MariaDB 10.3+ |
| Git | Any recent version, to clone the repository |
| Web server | Apache with `mod_rewrite` (XAMPP, InfinityFree) or the PHP built-in server for local use |

There is no Composer, Node, Python, or npm step. All front-end assets are vendored in `assets/`.

Check the essentials before you start:

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|openssl|curl|fileinfo'
mysql --version
```

On Windows, enable the extensions in `php.ini` by removing the leading `;` from `extension=pdo_mysql`, `extension=mbstring`, `extension=openssl`, and `extension=curl`.

### 2. Clone the repository

```bash
git clone https://github.com/johnlcstudios-alt/ICOTAS---Intelligent-Class-Observation-and-Teaching-Analysis-System.git icotas
cd icotas
```

`config/local.php` is git-ignored because it holds secrets. The installer creates it; never commit or publish it.

### 3. Create the database

The installer can create a missing database only when the host is `localhost`, `127.0.0.1`, or `::1`. On shared hosting, create the database and database user in the control panel first.

```bash
mysql -u root -p -e "CREATE DATABASE icotas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Use the credentials your host gives you if you are not running MySQL locally.

### 4. Install

#### Option A — browser installer (recommended)

1. Start the application (step 5) so the installer is reachable.
2. Open `http://127.0.0.1:8090/install.php` (or `https://your-site/install.php`).
3. Enter the database host, port, name, username, and password.
4. Review the institution name and system name, and set the application URL only if the site runs under a subdirectory or proxy (leave it blank otherwise).
5. Enter the first **Dean** account: first name, last name, username, email, and a password of at least 12 characters using at least three of uppercase, lowercase, digits, and symbols.
6. Submit. The installer writes `config/local.php`, imports all 44 tables from `database/schema.sql`, seeds the standard survey templates and the ISO/IEC 25010 UAT form, creates the Dean, generates the private developer key, and writes `storage/installed.lock`.
7. **Copy the developer URL shown once on the success screen.** It opens the unlinked developer console at `/developer/{key}` and is never linked in the interface. Treat it like a password.
8. Delete `install.php` from the server. The lock file also prevents reruns.

#### Option B — manual install (no browser installer)

Use this when the installer cannot run or shell access is unavailable:

1. Import the schema: `mysql -u root -p icotas < database/schema.sql`, or import `database/schema.sql` through phpMyAdmin.
2. Copy `config/local.sample.php` to `config/local.php` and fill in the real database credentials.
3. Set `app.key` to 64 random hex characters: `php -r "echo bin2hex(random_bytes(32));"`.
4. Set `app.developer_key` to a long random string if you want the developer console; leave it empty to disable it.
5. Insert the first Dean account with PHP `password_hash()` and `role = 'dean'`, then sign in.
6. Seed the standard survey templates and UAT form with `php tools/seed_defaults.php`, or create them in the interface.
7. Create `storage/installed.lock` when setup is complete.

### 5. Start the system

#### Local development (PHP built-in server)

From the repository root:

```bash
php -S 127.0.0.1:8090 tools/dev-router.php
```

The router maps every request to `index.php` and serves static files directly. Leave the command running and open **http://127.0.0.1:8090**. Sign in at `/login`.

To stop it, press `Ctrl+C`. If the port is busy, use another port (for example `8091`) and adjust the URL.

#### Apache / XAMPP

1. Copy or clone the project into the Apache document root, for example `C:\xampp\htdocs\icotas`, so that `index.php` is directly inside it.
2. Start Apache and MySQL from the XAMPP control panel.
3. Make sure `.htaccess` is honoured (`AllowOverride All` for the directory) and that `mod_rewrite` is enabled.
4. Make `config/` and `storage/` writable by the web server. The installer reports these checks if permissions are wrong.
5. Open `http://localhost/icotas/install.php`, install, then open `http://localhost/icotas/login`.

#### Shared hosting (InfinityFree)

Upload the files into `htdocs` so that `index.php` is at the web root, create the database in the control panel, then run the browser installer over HTTPS. Full steps, including the manual fallback, are in `documentation/deployment-infinityfree.md`.

### 6. After installation

- Sign in at `/login` with the Dean account you created.
- Create Academic Head, instructor, and student accounts under **Users** (`/dean/administrators` for administrators, `/dean/users` for everyone else). No invitation codes are involved.
- Store the developer URL securely if you generated one; rotate `app.developer_key` in `config/local.php` if it leaks.
- Delete `install.php` on any environment that can be reached from the internet.
- Optional demo data (never mix it with real records):

  ```bash
  php tools/seed_demo.php generate
  php tools/seed_demo.php status
  php tools/seed_demo.php reset
  ```

  Demo accounts use the password `Demo@12345`; see [Demonstration data](#demonstration-data).

### Reinstalling

The installer refuses to run while `storage/installed.lock` exists. To start over deliberately:

1. Back up the database and `config/local.php`.
2. Drop and recreate the database (or remove the old tables).
3. Delete `storage/installed.lock` and `config/local.php`.
4. Open `install.php` again.

### Troubleshooting

| Symptom | Fix |
|---|---|
| Blank page or HTTP 500 | Enable `display_errors` locally, or check `storage/logs/` and the Apache/PHP error log. Confirm required PHP extensions are enabled. |
| `could not find driver` | Enable `pdo_mysql` in `php.ini` and restart the server. |
| `Access denied for user` | Correct the host, database name, username, and password in `config/local.php` or rerun the installer. |
| 404 on every route except `/` | Apache is not reading `.htaccess`; enable `mod_rewrite` and `AllowOverride All`. |
| Installer says the installation is already configured | `storage/installed.lock` exists; see **Reinstalling**. |
| Login succeeds but you are returned to the sign-in page | PHP's session save path is not writable, or `app.base_url` does not match the URL you are using. |

## ICOTAS LIVE DEMO: icotas.kesug.com

---

## Student account creation

Students can create their own account from the sign-in page at `/signup`. The public form is limited to the **student** role; instructor, academic-head, and Dean accounts are still issued by the academic office.

- Self-registration is unavailable while demo mode is active, so synthetic demo data cannot acquire live accounts.
- The Dean can close registration at any time under **Settings → Evaluation access → Allow students to create their own accounts** (`allow_student_signup`).
- A new account is active and marked `is_demo = 0`, but it is **not enrolled** in any class and receives no evaluation eligibility. Access to an evaluation still requires the instructor's roster and an active QR session.
- Controls: CSRF, hidden honeypot field, 8 attempts/hour per network, unique username/email/student number, and an 8+ character password containing a letter and a number. Each registration is recorded as `user.signup` in the audit log.

---

## Account creation (no invitation codes)

There is **no invite code feature**. Deans and Academic Heads create accounts directly and hand over credentials themselves.

- **Administrator accounts** — open **Users → Administrators** (`/dean/administrators`). A Dean can create a Dean or Academic Head account; an Academic Head can create Academic Head accounts only. The creator supplies the profile and chooses either a generated temporary password (shown once, change required at first sign-in) or an explicit password.
- **Instructor, student, and staff accounts** — created by the Dean or Academic Head under **User management** (`/dean/users`) or through the CSV import. Instructor accounts receive a generated temporary password shown once and must change it at first sign-in.
- **Developer console** — the unlinked `/developer/{key}` page generated by the installer. It can manage every account, every table record, system settings, demo data, the audit log, and guarded SQL. It has no navigation entry; a wrong or missing key returns HTTP 404. Treat the URL like a password and rotate the key if it is exposed.

The administrator module and the developer console are protected by server-side role checks, not just hidden links. Only a Dean may create or grant the Dean role. An Academic Head cannot manage Dean records, and every administrative action is written to the audit log. Creating accounts is rate-limited.
- Manual password resets by an administrator also sign the account out and force a new password at the next sign-in. Voluntary password changes are available from the account menu for every role.
- Existing installations must run `database/migrations/2026_11_17_remove_registration_invites.sql` before deploying the matching files. It drops the old `registration_invites` table and the `allow_dean_signup` setting. Fresh installs from `database/schema.sql` already have no invite tables.

---

## Demonstration data

The demo dataset is **fictional, flagged `is_demo = 1`, and isolated from live records**. It is not research data.

```bash
php tools/seed_demo.php generate
php tools/seed_demo.php status
php tools/seed_demo.php reset
```

Baseline seed: 4 departments, 6 programs, 12 courses, 9 sections, 12 instructors, 90 students, 84 evaluation sessions, 729 synthetic responses, and 8 UAT respondents. Counts change when you interact with the demo.

Demo password for every seeded account: `Demo@12345`

| Role | Accounts |
|---|---|
| Dean | `demo.dean` |
| Academic Head | `demo.head.cba`, `demo.head.ccs` |
| Instructor | `demo.instructor.01` … `demo.instructor.12` |
| Student | `demo.student.001` … `demo.student.090` |

See `documentation/demo-data.md` before using demo mode in front of an audience.

---

## Local development and verification

```bash
# Main local server (uses the database in config/local.php)
php -S 127.0.0.1:8090 tools/dev-router.php

# Isolated test server
ICOTAS_DB_DATABASE=icotas_test php -S 127.0.0.1:8095 tools/dev-router.php
```

```bash
php tests/run.php
php tests/prepare.php
php tools/e2e.php
php tools/smoke.php --login=demo.dean
```

`tools/e2e.php` refuses to run unless the base URL is loopback on port 8090–8099 (default 8095) and the database is `icotas_test`. Never point it at an institutional database.

See `documentation/testing.md` for the current test evidence and the limits of that evidence.

---

## Documentation

| Document | Purpose |
|---|---|
| `documentation/architecture.md` | Request lifecycle, layers, directory responsibilities |
| `documentation/database.md` | 44-table schema, relationships, migrations, demo scoping |
| `documentation/rbac.md` | Role matrix and server-side enforcement |
| `documentation/anonymity.md` | Separation model, transaction flow, residual risks |
| `documentation/qr-lifecycle.md` | Token security, states, revoke, poster/print |
| `documentation/ai-and-prediction.md` | Rule-based AI labeling and transparent forecasting |
| `documentation/uat.md` | ISO/IEC 25010 process, thresholds, results |
| `documentation/testing.md` | Exact test evidence and scope limits |
| `documentation/security.md` | Controls, secrets, headers, limitations |
| `documentation/demo-data.md` | Demo accounts, isolation, reset behavior |
| `documentation/deployment-infinityfree.md` | Hosting guide, package, manual fallback |
| `documentation/research-alignment.md` | Research claims vs implementation results |
| `documentation/README.md` | Documentation index and reading order |

---

## Research and AI labeling rules

- The sentiment and theme component is labeled **“Rule-Based AI-Assisted Sentiment and Theme Analysis”** (`rule-v1.1`). It is deterministic keyword/rule matching, not a trained language model.
- Prediction output is **rule-based trend estimation** using a weighted moving average and least-squares slope. It is **not** a validated machine-learning model, and no accuracy percentage is claimed.
- Mixed sentiment is reported as `mixed`; positive and negative signals are not averaged into a falsely neutral result.
- Existing reported research values, new implementation results, synthetic demo data, unvalidated predictions, and future work are kept distinct in `documentation/research-alignment.md`.
- The original research manuscript was not available in the repository when this documentation was written. Research-value comparisons are therefore limited to the requirements captured in the project brief and must be reconciled with the manuscript before publication.

---

## License and institutional use

This repository was prepared for ACLC College Tacloban City. Confirm institutional permission and data-protection review before processing real student or faculty data.
