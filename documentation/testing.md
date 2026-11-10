# Testing and Verification Evidence

## 1. What was tested, and where

| Item | Value |
|---|---|
| Date of latest local run | 2026-11-10 |
| OS | Windows (XAMPP) |
| Web server | Apache 2.4.58 |
| PHP CLI | 8.0.30 (the code targets PHP 8.4; no 8.4 runtime was available locally) |
| Database | MariaDB 10.4.32 |
| Main database | `icotas` |
| Isolated test database | `icotas_test` |
| Isolated test server | `http://127.0.0.1:8091` |
| Main local server | `http://127.0.0.1:8090` |

**Scope statement:** these are local verification results. They do not establish InfinityFree compatibility, production load capacity, or security certification.

## 2. Automated suite

```powershell
C:\xampp\php\php.exe tests\prepare.php
C:\xampp\php\php.exe tests\run.php
```

`tests/prepare.php` rebuilds `icotas_test` from `database/schema.sql` and `DemoService`, then `tests/run.php` rebuilds it again before running. The suite refuses to touch the configured institutional database.

**Result: 34/34 tests passed.** Coverage includes:

| Area | Assertions |
|---|---|
| AI engine | Positive, negative, mixed, neutral/empty, Filipino terms, version transparency |
| Analytics | Source reconciliation, participation rate, 1–5 scale, live/demo isolation, instructor scope |
| Authentication | Credential hash, wrong password, five-failure lockout, session identity, logout |
| Anonymity | No student key in response tables, one response + separate participation, duplicate rejection, written feedback analysis |
| Prediction | Exact least-squares slope, weighted moving average, insufficiency output, transparent thresholds, stored method/metrics |
| QR | Hash + cipher storage, regeneration invalidates old token, close/archive/expired regeneration guards, time validation, revoke -> unresolvable, archived close/revoke guards, scheduled activation |
| UAT | Exact thresholds, invitation/submission transaction, duplicate rejection, invalid ratings |
| Security/session | Session guards and CSRF-adjacent flows exercised through HTTP tests |

## 3. HTTP end-to-end suite

Requires the isolated server on port 8091 with `ICOTAS_DB_DATABASE=icotas_test`.

```powershell
$env:ICOTAS_DB_DATABASE='icotas_test'
C:\xampp\php\php.exe -S 127.0.0.1:8091 tools/dev-router.php
```

In another terminal:

```powershell
C:\xampp\php\php.exe tests\prepare.php
C:\xampp\php\php.exe tools\e2e.php
```

`tools/e2e.php` refuses to run unless the port is 8091 and the configured database is `icotas_test`.

**Result: 55/55 checks passed.** It covers:

- Login redirect and success flash.
- Student 403 on dean and instructor areas; student portal 200.
- Department create/update/delete with scope inheritance.
- Settings update and restore.
- Student QR token decrypt, manual-code join, direct link, form render.
- Anonymous submission: exactly one response, participation marked submitted separately.
- Duplicate reopen: duplicate guard, no extra response.
- QR regenerate, close, archive, archived-revoke rejection.
- Revoke: flash, session closed, old link 404, token cipher rotated.
- Notifications read-all.
- CSV export (content type, populated) and PDF export (`%PDF`, non-trivial size).
- Demo reset: demo users/responses removed, live fixture survives, demo mode off.

## 4. Authenticated route smoke crawl

```powershell
C:\xampp\php\php.exe tools\smoke.php --login=demo.dean
C:\xampp\php\php.exe tools\smoke.php --login=demo.instructor.01
C:\xampp\php\php.exe tools\smoke.php --login=demo.student.001
```

**Result:** all preset pages returned HTTP 200 with expected titles:

- 33 dean/academic-head pages (dashboard, users, academic structure, surveys, assignments, QR sessions, analytics, AI, predictions, recommendations, reports, observations, UAT, notifications, audit, settings, demo).
- 8 instructor pages (dashboard, evaluations, analytics, predictions, recommendations, observations, reports, notifications).
- 4 student pages (dashboard, scan, history, notifications).

This crawl verifies rendering, not every mutation; mutations are covered by the automated and E2E suites.

## 5. Installer validation

```powershell
C:\xampp\php\php.exe tools\test-installer.php
```

The script backs up `config/local.php`, creates a scratch database `icotas_installer_test`, runs `install.php` in-process with a test Dean, verifies schema/Dean/settings/lock, then restores the config and removes the test lock.

**Result: 16/16 checks passed** in the latest validation run. It also confirmed the earlier `settings` → `system_settings` fix and that demo mode defaults off after installation.

Re-run this test after any installer change and before packaging.

## 6. Syntax checks

```powershell
Get-ChildItem app,config,tests,tools -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

`assets/` is excluded because it contains vendored JavaScript and fonts, not application PHP. All modified application files passed `php -l`.

## 7. Release package verification

The 2.0.0 archive was extracted to a clean directory and checked:

- 176 files; no `config/local.php`, `storage/installed.lock`, `tests/`, `tools/`, `unit/`, or Git metadata.
- All 123 packaged PHP files passed `php -l`.
- Root `.htaccess` and all protected-directory `.htaccess` files are present in the archive.
- The extracted copy booted on `http://127.0.0.1:8092`; `GET /login` returned 200 with the password form.
- A test router emulating the shipped deny rules returned 403 for `/config/local.php` and `/storage/installed.lock`.

These checks confirm archive completeness and bootability, not Apache `.htaccess` enforcement or InfinityFree behavior. Apache-level access controls remain part of the live-host checklist.

## 8. What is not verified

| Area | Status |
|---|---|
| PHP 8.4 runtime | Not tested. Code avoids 8.4-only syntax; local runtime was 8.0.30. |
| InfinityFree | Not tested. No account/credentials were available. |
| Apache under InfinityFree | Not tested. `.htaccess`, PHP version, MySQL host, and file limits must be confirmed. |
| Nginx / non-Apache | Not supported without equivalent deny/rewrite configuration; untested. |
| Browser camera scanning | jsQR is vendored, but camera permission and device behavior were not tested on physical phones. |
| QR PNG download | Client-side canvas path not exercised by the automated HTTP suite. |
| Print poster | Layout exists; print rendering was not pixel-verified. |
| Screen readers / WCAG audit | Not performed. |
| Load/concurrency | Duplicate submission is transaction-locked and tested logically; high-volume load was not benchmarked. |
| Backup/restore | Procedure documented; full restore drill not performed. |
| Email/SMS | Not implemented by design. |
| Security pen test | Not performed. |
| Data-protection review | Institutional review still required. |

## 9. Test data safety

- `tests/prepare.php` drops/rebuilds only `icotas_test`.
- `tools/e2e.php` aborts unless the port is 8091 and database is `icotas_test`.
- `tools/test-installer.php` uses `icotas_installer_test` and restores `config/local.php`.
- Demo reset deletes only `is_demo` records and switches demo mode off.
- Never run these tools against the institutional database.

## 10. Reproducing the evidence

1. Start MySQL and Apache (or the PHP development server).
2. Ensure `config/local.php` points to a disposable local database.
3. Run `tests/prepare.php`, then `tests/run.php`.
4. Start the 8091 server with `ICOTAS_DB_DATABASE=icotas_test`.
5. Run `tools/e2e.php`.
6. Run the three smoke presets against 8090.
7. Record the exact output in the deployment log or manuscript appendix.

If a check fails, fix the application rather than editing the expected output. Rebuild the test database before rerunning.
