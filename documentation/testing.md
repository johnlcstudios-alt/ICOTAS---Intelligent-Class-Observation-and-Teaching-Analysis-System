# Testing and Verification Evidence

## 1. What was tested, and where

| Item | Value |
|---|---|
| Date of latest local run | 2026-11-17 |
| OS | Windows (XAMPP) |
| Web server | Apache 2.4.58 |
| PHP CLI | 8.0.30 (the code targets PHP 8.4; no 8.4 runtime was available locally) |
| Database | MariaDB 10.4.32 |
| Main database | `icotas` |
| Isolated test database | `icotas_test` |
| Isolated test server | `http://127.0.0.1:8095` |
| Main local server | `http://127.0.0.1:8090` |

**Scope statement:** these are local verification results. They do not establish InfinityFree compatibility, production load capacity, or security certification.

## 2. Automated suite

```powershell
C:\xampp\php\php.exe tests\prepare.php
C:\xampp\php\php.exe tests\run.php
```

`tests/prepare.php` rebuilds `icotas_test` from `database/schema.sql` and `DemoService`, then `tests/run.php` rebuilds it again before running. The suite refuses to touch the configured institutional database.

**Result: 49/49 tests passed.** Coverage includes:

| Area | Assertions |
|---|---|
| Administrator accounts | Registry scoped to Dean/Academic Head and to demo/live scope, Dean-first ordering, role counts reconcile with records, direct creation stores role/scope/forced-change correctly, dean invitations hashed and single-purpose |
| Survey builder | Every question type stored in submitted order, one-based sequential ordering, option slugging, reordering with no duplication or loss, five malformed definitions refused without corrupting the draft, published versions immutable, new-version copy-on-write, duplication copies the published definition, template cloning |
| Recommendations | Complete playbook for every teaching theme, evidence-linked rows (theme count or category average below 3.50), regeneration refreshes instead of duplicating, one open recommendation per theme, instructor privacy and ownership-checked status changes, administrator override |
| AI engine | Positive, negative, mixed, neutral/empty, Filipino terms, version transparency |
| Analytics | Source reconciliation, participation rate, 1–5 scale, live/demo isolation, instructor scope |
| Authentication | Credential hash, wrong password, five-failure lockout, session identity, logout |
| Anonymity | No student key in response tables, one response + separate participation, duplicate rejection, written feedback analysis |
| Prediction | Exact least-squares slope, weighted moving average, insufficiency output, transparent thresholds, stored method/metrics |
| QR | Hash + cipher storage, regeneration invalidates old token, close/archive/expired regeneration guards, time validation, revoke -> unresolvable, archived close/revoke guards, scheduled activation |
| UAT | Exact thresholds, invitation/submission transaction, duplicate rejection, invalid ratings |
| Security/session | Session guards and CSRF-adjacent flows exercised through HTTP tests |

## 3. HTTP end-to-end suite

Requires the isolated server on port 8095 with `ICOTAS_DB_DATABASE=icotas_test`.

```powershell
$env:ICOTAS_DB_DATABASE='icotas_test'
C:\xampp\php\php.exe -S 127.0.0.1:8095 tools/dev-router.php
```

In another terminal:

```powershell
C:\xampp\php\php.exe tests\prepare.php
C:\xampp\php\php.exe tools\e2e.php
```

`tools/e2e.php` refuses to run unless the port is in the 8090-8099 development range (default 8095) and the configured database is `icotas_test`.

**Result: 171/171 checks passed.** It covers:

- Demo-mode registration gate and explanation.
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
- Live self-registration: required-field and password-mismatch rejection, success, student role, `is_demo = 0`, active status, password hash, audit entry, duplicate username/student number, honeypot discard, CSRF 419, sign-in and portal access, and disabled-setting gate.
- Administrator accounts (Dean and Academic Head): module renders for a Dean; direct creation returns a one-time generated password, stores the requested role, `is_demo = 0`, and `must_change_password = 1`; the credential panel disappears after the redirect; duplicate and incomplete submissions are refused without writing a row; the new administrator is forced through `/account/password` and then reaches the administrative dashboard.
- Privilege escalation: an Academic Head receives 403 on the administrator module and its POST endpoint, is not offered the Dean role in the user form, is refused when the `dean` role is forged in the request body, cannot see Dean accounts in the user directory, and cannot open or deactivate a Dean record.
- Dean invitations from the module: code displayed once, only the SHA-256 hash stored, redemption creates exactly one live Dean account and consumes one use, a revoked code creates no account.
- Test-harness robustness: `clearRateLimit()` deletes rate-limit buckets directly because the harness process cannot compute the server's IP hash; loopback requests bypass any machine-level proxy (`CURLOPT_NOPROXY`).

## 4. Authenticated route smoke crawl

```powershell
C:\xampp\php\php.exe tools\smoke.php --login=demo.dean
C:\xampp\php\php.exe tools\smoke.php --login=demo.head.ccs
C:\xampp\php\php.exe tools\smoke.php --login=demo.instructor.01
C:\xampp\php\php.exe tools\smoke.php --login=demo.student.001
```

**Result:** all preset pages returned HTTP 200 with expected titles:

- 34 dean pages (dashboard, users, administrator accounts, academic structure, surveys, assignments, QR sessions, analytics, AI, predictions, recommendations, reports, observations, UAT, notifications, audit, settings, demo).
- 9 academic-head pages (the same administrative surface minus the Dean-only administrator module).
- 8 instructor pages (dashboard, evaluations, analytics, predictions, recommendations, observations, reports, notifications).
- 4 student pages (dashboard, scan, history, notifications).

This crawl verifies rendering, not every mutation; mutations are covered by the automated and E2E suites.

## 5. Installer validation

```powershell
C:\xampp\php\php.exe tools\test-installer.php
```

The script backs up `config/local.php`, creates a scratch database `icotas_installer_test`, runs `install.php` in-process with a test Dean, verifies schema/Dean/settings/lock, then restores the config and removes the test lock.

**Result: 17/17 checks passed** in the latest validation run. It also confirmed the earlier `settings` → `system_settings` fix and that demo mode defaults off after installation.

Re-run this test after any installer change and before packaging.

## 6. Syntax checks

```powershell
Get-ChildItem app,config,tests,tools -Recurse -Filter *.php |
  ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

`assets/` is excluded because it contains vendored JavaScript and fonts, not application PHP. All modified application files passed `php -l`.

## 7. Release package verification

The 2.1.0 archive (adding the Dean/Academic Head account module and the new test suites) was built, extracted to a clean directory, and checked:

- 190 files, 893,886 bytes, SHA-256 recorded in `dist/RELEASE.txt` and `dist/ICOTAS-InfinityFree-v2.1.0.sha256`.
- No `config/local.php`, `storage/installed.lock`, `tests/`, `tools/`, `unit/`, `.git/`, `.freebuff/`, or `dist/`.
- All 133 packaged PHP files passed `php -l`; 7 `.htaccess` files and all 8 vendored assets are present; `storage/` ships only `.htaccess` and `.gitkeep` placeholders.
- The extracted copy booted with a generated `config/local.php`: `GET /login` returned 200 with the sign-in form and CSRF token, `GET /dean/administrators` returned 302 → `/login`, and `GET /install.php` returned 200 with every server requirement satisfied.

### Apache access-control verification (local, subdirectory layout)

The workspace copy was requested through XAMPP Apache 2.4 at
`http://localhost/ICOTAS/ICOTAS---Intelligent-Class-Observation-and-Teaching-Analysis-System` to confirm the root `.htaccess` actually blocks sensitive paths rather than merely containing rules:

| Request | Result | Expected |
|---|---|---|
| `/login` | 200 | 200 |
| `/dean/administrators` (guest) | 302 → `/login` | 302 |
| `/assets/css/app.css` | 200 | 200 |
| `/config/local.php`, `/config/app.php` | 403 | 403 |
| `/app/Controllers/AuthController.php` | 403 | 403 |
| `/database/schema.sql` | 403 | 403 |
| `/storage/.htaccess` | 403 | 403 |
| `/documentation/testing.md` | 403 | 403 |
| `/README.md` | 403 | 403 |
| `/install.php` | 200 | 200 |

**11/11 passed.** This verifies the local Apache rules under a subdirectory install. It does not verify InfinityFree's Apache configuration, which remains part of the live-host checklist.

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
- `tools/e2e.php` aborts unless the port is in the 8090-8099 development range (default 8095) and database is `icotas_test`.
- `tools/test-installer.php` uses `icotas_installer_test` and restores `config/local.php`.
- Demo reset deletes only `is_demo` records and switches demo mode off.
- Never run these tools against the institutional database.

## 10. Reproducing the evidence

1. Start MySQL and Apache (or the PHP development server).
2. Ensure `config/local.php` points to a disposable local database.
3. Run `tests/prepare.php`, then `tests/run.php`.
4. Start the 8095 server with `ICOTAS_DB_DATABASE=icotas_test`.
5. Run `tests/prepare.php` again, then `tools/e2e.php` (the E2E suite expects fresh demo data and demo mode on).
6. Run the four smoke presets against 8090.
7. Record the exact output in the deployment log or manuscript appendix.

If a check fails, fix the application rather than editing the expected output. Rebuild the test database before rerunning.
