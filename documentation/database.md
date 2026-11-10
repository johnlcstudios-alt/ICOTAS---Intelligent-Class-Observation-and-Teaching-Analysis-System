# Database

## 1. Overview

- **Engine:** InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
- **Objects:** 43 tables plus seed rows in `roles`, `system_settings`, `prediction_configurations`, and `uat_reference_results`.
- **Access:** PDO with native prepared statements and exceptions. Identifiers are never interpolated from user input; the few dynamic identifiers are validated against fixed allowlists.
- **Schema file:** `database/schema.sql`, imported by `install.php` or phpMyAdmin.
- **Migrations:** None. Schema changes require a reviewed SQL patch and a new import for fresh installs. Back up before upgrading an existing database.

`database/schema.sql` is idempotent (`CREATE TABLE IF NOT EXISTS`, `INSERT ... ON DUPLICATE KEY UPDATE` for settings). It does not drop existing tables.

## 2. Table groups

### Identity and governance (8)

| Table | Purpose |
|---|---|
| `roles` | `dean`, `academic_head`, `instructor`, `student` |
| `users` | Accounts, role, academic placement, status, `is_demo`, password hash |
| `user_sessions` | Server-side session metadata and revocation support |
| `login_attempts` | Per-account/IP failure history for throttling |
| `rate_limits` | Network-level request buckets (for example `auth.login`) |
| `password_reset_tokens` | Reserved table; no public reset flow is exposed in this build |
| `audit_logs` | Actor, action, entity, description, hashed IP, timestamp |
| `notifications` | In-app user notifications; no email transport |

### Academic structure (10)

| Table | Purpose |
|---|---|
| `departments` | Organizational units |
| `programs` | Programs under departments |
| `academic_years` | School-year periods |
| `semesters` | Ordered semesters within a year |
| `courses` | Course catalog |
| `sections` | Class sections and capacity |
| `student_enrollments` | Student ↔ course/section/period registration |
| `instructor_assignments` | Instructor teaching load |
| `class_schedules` | Day/time/room schedule per teaching load |
| `class_observations` | Dean-observed classroom observation records |

### Survey definitions (5)

| Table | Purpose |
|---|---|
| `surveys` | Survey identity, category, template flag, demo flag |
| `survey_versions` | Immutable published snapshots; draft/published status |
| `survey_questions` | Versioned questions, type, category, order, scale bounds |
| `question_options` | Choice options for single/multiple-choice questions |
| `survey_templates` | Reusable template catalog linked to published surveys |

Published versions are not edited in place. The builder creates a new draft version for changes; assignments pin a specific `survey_version_id`.

### Evaluation and anonymity (6)

| Table | Purpose |
|---|---|
| `evaluation_assignments` | Instructor + course + section + period + survey version + window |
| `evaluation_sessions` | QR session, code, token hash, encrypted display token, lifecycle status |
| `evaluation_participation` | Per-session eligibility and submission state — **contains `student_id`** |
| `evaluation_eligibility_tokens` | Per-student eligibility token hash and redemption timestamp |
| `evaluation_responses` | Anonymous response envelope — **no student identifier** |
| `response_answers` | Answers linked to response and question — **no student identifier** |

`evaluation_responses.submitted_at` is deliberately bucketed to the start of the hour to reduce timing correlation. `submitted_on` stores the date for period grouping. `source` distinguishes `live` from `demo`.

### Analytics, AI, and decisions (6)

| Table | Purpose |
|---|---|
| `sentiment_results` | One rule-based analysis per response: label, scores, confidence, engine version, excerpt |
| `feedback_themes` | Theme/polarity/weight/keyword matches per response |
| `analytics_snapshots` | Cached or generated aggregate snapshots |
| `prediction_configurations` | Transparent threshold mirror used by prediction screens |
| `prediction_results` | Immutable estimate records with metrics, explanation, thresholds |
| `instructor_recommendations` | Evidence-linked recommendations with status |

### Reporting (1)

| Table | Purpose |
|---|---|
| `report_history` | Requester, report type, filters, format, timestamp |

### UAT (5 + 1 reference)

| Table | Purpose |
|---|---|
| `uat_surveys` | UAT instrument, status, window, demo flag |
| `uat_questions` | ISO/IEC 25010 characteristics and statements |
| `uat_respondents` | Invitation identity, type, token hash/cipher, status |
| `uat_responses` | Submitted UAT response header |
| `uat_answers` | 1–5 ratings per question |
| `uat_reference_results` | Optional storage for reported reference values with source labels; **empty by default** |

UAT is an identified, invitation-controlled evaluation of the system. It is not the anonymous student evaluation channel.

### Settings (1)

| Table | Purpose |
|---|---|
| `system_settings` | Key/value settings by group, with optional `updated_by` |

## 3. Key relationships

```
roles 1 ── * users
departments 1 ── * programs 1 ── * courses
academic_years 1 ── * semesters
users (student) * ── * courses/sections  (student_enrollments)
users (instructor) * ── * courses/sections (instructor_assignments)

surveys 1 ── * survey_versions 1 ── * survey_questions 1 ── * question_options

survey_versions 1 ── * evaluation_assignments
evaluation_assignments 1 ── * evaluation_sessions 1 ── * evaluation_participation
evaluation_sessions 1 ── * evaluation_responses 1 ── * response_answers
evaluation_responses 1 ── 1 sentiment_results
evaluation_responses 1 ── * feedback_themes

users (instructor) 1 ── * prediction_results
users (instructor) 1 ── * instructor_recommendations

uat_surveys 1 ── * uat_questions
uat_surveys 1 ── * uat_respondents 1 ── 1 uat_responses 1 ── * uat_answers
```

The critical boundary is that **`evaluation_participation` and `evaluation_responses` share only `evaluation_session_id`**, not a student key. There is no foreign key or column that joins a response to a student.

## 4. Demo/live scoping

Thirteen tables carry `is_demo`:

`departments`, `programs`, `courses`, `sections`, `student_enrollments`, `instructor_assignments`, `class_schedules`, `class_observations`, `surveys`, `evaluation_assignments`, `evaluation_sessions`, `uat_surveys`, `users`.

Scope rules:

- The active scope is `system_settings.demo_mode` (`1` = demo, `0` = live).
- Records with `is_demo` are filtered in list, edit, analytics, prediction, report, and QR flows.
- `evaluation_participation` inherits scope from its session.
- `evaluation_responses` inherits scope from its session.
- `response_answers`, `sentiment_results`, and `feedback_themes` inherit scope through their response.
- UAT responses use `source = 'demo'|'live'` and their survey's `is_demo`.

See `demo-data.md` for reset behavior.

## 5. Status vocabularies

| Entity | Values |
|---|---|
| User | `active`, `inactive`, `locked` (status enforcement is applied at login) |
| Assignment | `draft`, `scheduled`, `active`, `closed`, `archived` |
| Session | `scheduled`, `active`, `expired`, `closed`, `archived` |
| Survey / version | `draft`, `published`, `archived` |
| Participation | `pending`, `submitted` |
| UAT survey | `draft`, `active`, `closed`, `archived` |
| UAT respondent | `invited`, `submitted` |
| Recommendation | `open`, `acknowledged`, `in_progress`, `resolved` |
| Prediction | `improving`, `stable`, `needs_attention`, `insufficient_data` |

## 6. Integrity and performance notes

- Foreign keys use `ON DELETE CASCADE` for dependent academic/evaluation records and `SET NULL` for actor references, preserving audit history where possible.
- Unique keys protect usernames, emails, employee/student numbers, session codes, token hashes, and per-session student participation.
- Indexes cover role, department, program, section, status, session, survey, response date, and instructor lookup paths.
- Aggregates use SQL `AVG`, `COUNT`, and grouped queries; no aggregate is stored as a substitute for source rows.
- Reports and analytics read the same source tables as the dashboards, so totals reconcile.

## 7. Backup and restore

Back up both the database and `config/local.php` (the application key is required to decrypt re-displayable QR/UAT tokens).

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root icotas > backup-icotas.sql
```

Restore:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root icotas < backup-icotas.sql
```

After restoring on a different host, update `config/local.php` database credentials and base URL. Existing encrypted tokens remain readable only with the same application key.
