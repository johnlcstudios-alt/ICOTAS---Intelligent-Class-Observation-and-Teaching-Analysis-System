# Role-Based Access Control

## 1. Roles

| Slug | Display name | Scope |
|---|---|---|
| `dean` | Dean | Full institutional administration, including Dean/Academic Head account provisioning |
| `academic_head` | Academic Head | The same administrative routes as `dean` except administrator-account provisioning and Dean records |
| `instructor` | Instructor | Own teaching data only |
| `student` | Student | Own evaluation portal, eligible sessions only |

Public access is limited to `/`, `/login`, `/signup` (when student self-registration is enabled), `/dean-signup` (when Dean invitation registration is enabled), `/uat/respond/{token}` (invitation-controlled UAT), and static assets. `/evaluate/{token}` requires an authenticated student and per-session eligibility.

The developer console at `/developer/{key}` sits outside the user RBAC model: its credential is a high-entropy key in `config/local.php`, not a user session. It is not linked in navigation, is not a role, and returns 404 unless the exact key matches. Anyone holding that URL can issue Dean invitation codes, so it must be treated as a deployment secret.

## 2. Enforcement layers

Authorization is enforced in three places, not only in the UI:

1. **Route middleware** (`app/routes.php`):
   - `auth` — authenticated session required.
   - `guest` — only unauthenticated users.
   - `admin` — `dean` or `academic_head`.
   - `role:instructor` / `role:student` — exact role.
2. **Controller checks**:
   - `inScope()` rejects a record from the other demo/live scope.
   - Instructor actions compare the record's `instructor_id` to `Auth::id()` before reading or changing it.
   - Student access checks a `pending` participation row for the exact session.
   - Report type allowlists prevent institution-wide reports for instructors.
3. **Model/service queries**:
   - Instructor analytics, predictions, recommendations, and reports filter by the authenticated instructor ID.
   - Student queries filter by the authenticated student ID.
   - Responses are read through sessions, never by student identity.

The navigation menu is role-aware but is **not** the security boundary; direct URL requests hit the same middleware and controller checks.

## 3. Capability matrix

Legend: ✅ allowed, — not available, ◐ limited to own records.

| Capability | Dean / Academic Head | Instructor | Student |
|---|---|---|---|
| Institutional dashboard | ✅ | — | — |
| User accounts, CSV import, password reset | ✅ (all roles) | ◐ instructor/student/academic-head only; Dean records are refused | — |
| Administrator accounts module (`/dean/administrators`) | ✅ Dean only | — (403) | — |
| Create a Dean or Academic Head account directly | ✅ | — (403) | — |
| Issue/revoke Dean invitation codes from inside ICOTAS | ✅ | — | — |
| Departments, programs, years, semesters, courses, sections, schedules, enrollments, teaching loads | ✅ | — | — |
| Survey builder, templates, publish/new version/duplicate/archive | ✅ | — | — |
| Evaluation assignments | ✅ | — | — |
| QR sessions: create, display, PNG, print, regenerate, close, revoke, archive | ✅ | — | — |
| Teaching analytics | ✅ institutional | ◐ own only | — |
| Rule-based AI insights | ✅ institutional | — (own analytics shown in dashboard) | — |
| Predictions / trend indicators | ✅ all instructors | ◐ own only | — |
| Recommendations | ✅ all, update status | ◐ own, update status | — |
| Class observations | ✅ create and manage | ◐ read own | — |
| Reports and exports | ✅ all report types | ◐ approved own-scope types | — |
| UAT management | ✅ | — | — |
| UAT response by invitation | — | — | ✅ via token (no login required) |
| Audit logs | ✅ | — | — |
| System settings | ✅ | — | — |
| Demo data generate/reset | ✅ | — | — |
| Student evaluation dashboard | — | — | ✅ |
| Create a personal student account | — | — | ✅ when enabled and not in demo mode |
| Self-register a Dean account with an invitation code | ✅ own account via `/dean-signup` | — | — |
| Change own password | ✅ | ✅ | ✅ |
| Issue or revoke Dean invitation codes | Developer URL key, not a user role | — | — |
| QR scan, manual code, direct link | — | — | ✅ eligible sessions only |
| Submit anonymous evaluation | — | — | ✅ once per session |
| Evaluation history | — | — | ✅ own participation status only |
| Notifications | ✅ own | ✅ own | ✅ own |

## 3a. Administrator account provisioning

Two supported paths create an administrative account:

1. **Direct creation** — a Dean opens `/dean/administrators`, supplies the profile, and ICOTAS either generates a temporary password (change required at first sign-in, shown once) or accepts an explicit password.
2. **Invitation** — a Dean (or the unlinked developer console) issues a single-use, hashed invitation code; the recipient redeems it at `/dean-signup` and chooses their own password.

Privilege-escalation rules enforced in code, not only in the UI:

- `/dean/administrators` and every create/invite/revoke action carry `['auth', 'role:dean']`; an Academic Head receives 403.
- `UserController::guardAssignableRole()` refuses the `dean` role for any account that is not a Dean, so a forged `role=dean` in `/dean/users` or a CSV import row is rejected.
- `UserController::guardAdministrativeTarget()` refuses an Academic Head any edit, status change, or password reset on a Dean or Academic Head record other than their own.
- An Academic Head's user directory excludes Dean accounts, and the Dean role is filtered out of their role dropdown.
- `POST /dean/administrators` is rate-limited (`administrator.create`, 30/hour) and every creation, invitation, and revocation is written to `audit_logs`.
- The last active Dean cannot be demoted or deactivated (pre-existing guard).

Creating an account never bypasses authentication: a generated password always sets `must_change_password = 1`, and the `auth` middleware confines that session to `/account/password` until the change is made.

## 4. Instructor privacy rules

An instructor may see:

- Aggregate means, distributions, themes, sentiment, and trends for their own sessions.
- Their own class observation feedback.
- Their own recommendations and report types.
- Anonymous written comments only when the session has at least the configured privacy threshold and comments are enabled.
- Participation counts, but not which student submitted or what any student answered.

An instructor may **not**:

- Open another instructor's session, analytics, predictions, reports, or observations.
- Edit surveys, assignments, sessions, responses, or UAT records.
- Identify a student from a response, timestamp, or comment.
- Generate institution-wide reports (`department`, `course` across others, `question_statistics`, `executive`, `uat` are excluded from the instructor allowlist).

`ReportService::allowedFor()` and `typesFor()` enforce the report allowlist on both the index and export actions; the export action re-checks the requested type, so hiding a report in the UI is not sufficient to generate it.

## 5. Student access rules

A student may:

- Create a personal account from `/signup` when the Dean has left registration enabled; this creates a student account only and does not enroll the student.
- Sign in to the mobile-first portal.
- See open evaluations for which a `pending` participation row exists.
- Join by QR scan, direct link, or manual session code.
- Submit one anonymous response per session.
- See their own completion history (status only, not response contents).

A student may **not**:

- Reach dean or instructor routes (403).
- Open an evaluation for another section, a closed/expired/archived session, or a session without eligibility.
- Submit twice; the participation row is locked and marked `submitted` in the same transaction.
- See who else participated or any aggregate results.

## 6. UAT access

UAT invitations are intentionally public-by-token so external evaluators can respond without an account. The token is 24 random bytes, stored as SHA-256 plus an encrypted copy for authorized re-display. Submission is single-use and transaction-locked. UAT is identified by design (respondent name/type) and is separate from anonymous student evaluations.

## 7. Administrative scope note

`academic_head` accounts share the administrative route set with `dean` **except** administrator-account provisioning (`/dean/administrators`) and any change to a Dean record, which are Dean-only.

Academic Head access is not yet department-limited: an Academic Head can still see and manage instructors, students, courses, sections, surveys, sessions, and reports institution-wide. If the institution requires department-scoped Academic Head access, that is a future enhancement: add a department scope to `users`, filter administrative queries by it, and add tests before deployment. Do not assume the current build enforces department boundaries.

## 8. Verification

RBAC is covered by:

- HTTP end-to-end checks: student receives 403 on dean and instructor dashboards; an Academic Head receives 403 on `/dean/administrators`, on the create/POST endpoint, on a forged `role=dean` submission, and on opening or deactivating a Dean record; the Dean role is absent from the Academic Head user form and directory.
- HTTP end-to-end onboarding checks: a wrong developer key returns 404, an invitation creates one live Dean account, single-use and revoked codes cannot be reused, and an instructor with a temporary password is confined to `/account/password`.
- Automated tests: instructor analytics are restricted to that instructor; qr/session and UAT access checks; invitation hashing/consumption/revocation; forced-change flag lifecycle.
- Smoke crawl: each role's own pages return HTTP 200.
- Manual review: every protected route in `app/routes.php` carries middleware.

See `testing.md` for commands and evidence.
