# ICOTAS Development Progress

**System:** ICOTAS — Intelligent Class Observation & Teaching Analytics System with QR-Code Monitoring & Predictive Insights
**Institution:** ACLC College Tacloban City
**Target:** PHP 8.4-compatible source, no Python/Node/cron/queue dependency, InfinityFree Free plan deployment
**Last updated:** 2026-11-17

This file is the running status record. Update it whenever a milestone moves. It records what is **implemented and verified**, what is **implemented but only partially verified**, and what is **not done**. A feature is never marked complete here because it renders; it is marked complete when a test or a recorded manual verification covers it.

---

## 1. Milestone status

| # | Milestone | Status | Evidence |
|---|---|---|---|
| 1 | Project foundation (architecture, PDO, routing, sessions, auth, RBAC, layouts) | **Complete** | `tests/run.php` auth suite (6 checks), route middleware review, smoke crawl for all roles |
| 2 | Academic management (users, departments, programs, years, semesters, courses, sections, schedules, loads, enrollments) | **Complete** | E2E department CRUD, smoke crawl of all 10 academic entities |
| 3 | Survey builder (CRUD, drag-and-drop editor, question types, templates, preview, versioning) | **Complete** | 5 automated survey tests (types, ordering, validation, immutability, versioning, duplication, templates) + smoke crawl |
| 4 | QR evaluation (assignments, scheduling, secure QR, browser scanner, eligibility, duplicate prevention, anonymous submission, participation) | **Complete** | 7 QR tests; E2E QR lifecycle, anonymous submission, duplicate guard |
| 5 | Analytics (statistics, instructor/department/participation analytics, history, charts) | **Complete** | 5 analytics tests; E2E and smoke crawl of analytics screens |
| 6 | AI analytics (sentiment, themes, keywords, executive summary, recommendations) | **Complete** | 6 AI tests + 4 recommendation tests (playbooks, evidence linkage, idempotency, ownership) |
| 7 | Predictive analytics (history, rule warnings, moving average, trend forecast, risk, explanation) | **Complete** | 5 prediction tests including sufficiency refusal and stored method/metrics |
| 8 | Reports and notifications (CSV, PDF, printable HTML, notification center, audit logs, UAT reporting) | **Complete** | E2E CSV + PDF export, notification read-all, audit log screen |
| 9 | Quality assurance (functional, integration, security, privacy, mobile, performance) | **In progress** | 40 automated tests + 171 HTTP checks pass; physical-device, WCAG, load and pen testing are not done |
| 10 | Deployment (installer, SQL export, package, InfinityFree test, manuals) | **In progress** | Installer validated 16/16 locally; InfinityFree deployment **not yet performed** |

---

## 2. Change log

### 2026-11-17 — Invitation codes removed; developer console expanded

**Removed**

- `InviteService`, the `/dean-signup` route and view, the `registration_invites` table, and the `allow_dean_signup` setting. Migration: `2026_11_17_remove_registration_invites.sql`.
- Dean self-registration and every invitation-related UI, test, and route.

**Added**

- Direct account creation for both administrative roles at `/dean/administrators`: no codes, with a generated temporary password or an explicit password.
- An unlinked developer console at `/developer/{key}` that manages users, any table row, settings, demo data, audit history, and guarded SQL.

**Changed**

- Academic Heads may now use `/dean/administrators` for Academic Head accounts; only a Dean may create or manage Dean accounts.

### 2026-11-17 — Administrator account provisioning (Dean-only)

**Added**

- `app/Controllers/AdministratorController.php` — Dean-only module at `/dean/administrators`.
  - Direct creation of a **Dean** or **Academic Head** account, with a generated temporary password (shown once, `must_change_password = 1`) or a password the Dean sets.
  - Invitation path: issue and revoke single-use, hashed Dean invitation codes that are redeemed at `/dean-signup`.
  - Registry of administrator accounts with status, last sign-in, and one-click manage/deactivate.
- `app/Views/dean/administrators/index.php` — module UI (registry, creation form, invitation panel, one-time credential panels).
- `app/Models/User.php` — `administrators()`, `countAdministrators()`, `countByRole()`, and an `exclude_role` filter on `paginate()`.
- Navigation entry (Dean only) and a link from User management.
- `tests/administrators_test.php` — 3 automated tests.
- 36 new HTTP end-to-end checks (module, escalation refusals, invitation lifecycle).

**Hardened (privilege escalation)**

- Only a Dean may create or grant the `dean` role (`guardAssignableRole()`), including via CSV import.
- Only a Dean may edit, deactivate, or reset the password of a Dean or another administrator (`guardAdministrativeTarget()`); an administrator may still manage their own record.
- Academic Heads no longer see Dean accounts in the user directory and are not offered the Dean role.
- `POST /dean/administrators` is rate-limited (`administrator.create`) and fully audited.

**Fixed**

- `tools/e2e.php` could not bind the reserved port 8091 on this workstation (held by an unrelated vendor service) and its `RateLimiter::clear()` could not clear the server's rate-limit bucket because the harness runs in a separate process:
  - default test port is now the 8090–8099 development range (default **8095**) on loopback only;
  - `clearRateLimit()` deletes bucket rows directly;
  - loopback requests bypass machine-level proxies (`CURLOPT_NOPROXY`);
  - the instructor password assertion no longer fatals on a missing row.

### 2026-11-17 — Test-gap closure, package rebuild, access-control verification

**Added**

- `tests/survey_test.php` (5 tests) — order-preserving persistence for every question type, option slugging, reordering, five malformed-definition refusals that leave the draft intact, published-version immutability, copy-on-write versioning, duplication, and template cloning.
- `tests/recommendations_test.php` (4 tests) — complete playbook for every theme, stored evidence linkage, idempotent regeneration with one open recommendation per theme, instructor privacy, and ownership-checked status transitions.

**Built and verified**

- `dist/ICOTAS-InfinityFree-v2.1.0.zip` (190 files, 893,886 bytes, SHA-256 recorded). All 133 packaged PHP files lint clean; `storage/` carries only placeholders; no secrets, tests, tools, or runtime data.
- Clean-copy boot verified (`/login` 200 with CSRF token, `/dean/administrators` 302 → `/login`, `/install.php` 200 with all requirements satisfied).
- Apache 2.4 access-control verification in the real subdirectory layout: 11/11 requests behaved as configured (source, config, SQL, documentation, README and hidden files → 403; routes and assets served).

### Earlier

- 2026-11-10 — Dean invitation registration, forced password change, theme refresh, student self-registration, installer fix (`settings` → `system_settings`), v2.0.0 package.

---

## 3. Current verification status

Run with the XAMPP PHP binary (`C:\xampp\php\php.exe`). The PHP on `PATH` has no `openssl` or `mbstring`, so the application refuses to run under it.

| Suite | Command | Result |
|---|---|---|
| Automated | `C:\xampp\php\php.exe tests\run.php` | **49/49 passed** (2.8 s) |
| HTTP end-to-end | `C:\xampp\php\php.exe tools\e2e.php` | **171/171 passed** (isolated `icotas_test`, port 8095) |
| Route smoke | `tools\smoke.php` for dean, academic head, instructor, student | **All routes HTTP 200** (34 dean, 9 academic head, 8 instructor, 4 student) |
| Installer | `C:\xampp\php\php.exe tools\test-installer.php` | **17/17 passed** |
| Package | `dist/ICOTAS-InfinityFree-v2.1.0.zip` | 190 files, 133 PHP files lint clean, clean boot verified |
| Apache access control | XAMPP Apache 2.4 subdirectory layout | **11/11 passed** (source, config, SQL, docs and hidden files return 403; routes and assets served) |

Database scope rules for these tools are documented in `testing.md` §9. None of them may be pointed at the institutional database.

---

## 4. Known defects and limitations

| Item | Severity | Status |
|---|---|---|
| InfinityFree deployment not performed | Deploy blocker for the claim, not for the code | Open — procedure in `deployment-infinityfree.md` |
| Browser camera QR scanning not tested on physical devices | Medium | Open |
| QR PNG download and print poster not pixel-verified | Low | Open |
| No department-scoped Academic Head access (institution-wide visibility) | Medium | Open — documented in `rbac.md` §7 |
| ~~No automated test for the survey builder save/reorder path~~ | — | **Closed** — `tests/survey_test.php` (5 tests) |
| ~~No automated test for `RecommendationService` output~~ | — | **Closed** — `tests/recommendations_test.php` (4 tests) |
| Rule-based AI has no measured accuracy/precision/recall | By design | Documented limitation |
| Predictions are unvalidated estimates | By design | Documented limitation |
| UAT reference values from the manuscript are not entered (manuscript absent) | Research blocker | Open |
| No MFA, no per-token brute-force throttle | Medium | Future work |

---

## 5. Database changes in this cycle

Migration `2026_11_17_remove_registration_invites.sql` drops `registration_invites` and deletes the `allow_dean_signup` setting from existing installations. Fresh installs from `database/schema.sql` already match the new state. The administrator and developer modules otherwise reuse `users`, `roles`, `audit_logs`, `rate_limits`, and `user_sessions`.

---

## 6. Next development tasks

1. Deploy to InfinityFree and record the smoke evidence required by `deployment-infinityfree.md`.
2. Test QR camera scanning and poster printing on physical Android/iOS devices over HTTPS.
3. Enter verified manuscript UAT reference values into `uat_reference_results` when the manuscript is available.
4. Consider department-scoped Academic Head access and MFA for administrative accounts.
5. Consider an automated test for the CSV user import path (currently covered manually and by import validation code review).
