# Role-Based Access Control

## 1. Roles

| Slug | Display name | Scope |
|---|---|---|
| `dean` | Dean / Academic Head | Full institutional administration |
| `academic_head` | Academic Head | Same administrative routes as `dean` in this build |
| `instructor` | Instructor | Own teaching data only |
| `student` | Student | Own evaluation portal, eligible sessions only |

Public access is limited to `/`, `/login`, `/uat/respond/{token}` (invitation-controlled UAT), and static assets. `/evaluate/{token}` requires an authenticated student and per-session eligibility.

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
| User accounts, CSV import, password reset | ✅ | — | — |
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
| QR scan, manual code, direct link | — | — | ✅ eligible sessions only |
| Submit anonymous evaluation | — | — | ✅ once per session |
| Evaluation history | — | — | ✅ own participation status only |
| Notifications | ✅ own | ✅ own | ✅ own |

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

`academic_head` accounts currently share the full administrative route set with `dean`. If the institution requires department-limited Academic Head access, that is a future enhancement: add a department scope to `users`, filter administrative queries by it, and add tests before deployment. Do not assume the current build enforces department boundaries.

## 8. Verification

RBAC is covered by:

- HTTP end-to-end checks: student receives 403 on dean and instructor dashboards.
- Automated tests: instructor analytics are restricted to that instructor; qr/session and UAT access checks.
- Smoke crawl: each role's own pages return HTTP 200.
- Manual review: every protected route in `app/routes.php` carries middleware.

See `testing.md` for commands and evidence.
