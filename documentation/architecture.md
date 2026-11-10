# Architecture

## 1. Design goals

ICOTAS is a server-rendered, dependency-light application intended for shared PHP hosting:

- No build step, package manager, background worker, or external service at runtime.
- Every protected action is authorized server-side; hidden UI is not treated as security.
- Anonymous evaluation responses are structurally separated from student identity.
- Rule-based analytics are transparent and reproducible.
- Demo records are isolated by an explicit scope flag and are safely resettable.
- The entire application can live under a fixed `htdocs` document root.

## 2. Runtime stack

| Component | Version / note |
|---|---|
| Apache | 2.4 with `mod_rewrite`; `.htaccess` allowed |
| PHP | Target 8.4; syntax kept compatible with 8.0+. Local verification used 8.0.30. |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+; local verification used MariaDB 10.4.32 |
| PDO MySQL | Prepared statements, exceptions, native prepares |
| Browser assets | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.4, jsQR 1.4.0, qrcodejs 1.0.0, all local |

There is no Python, Node, PostgreSQL, Redis, WebSocket, cron, or paid AI API.

## 3. Request lifecycle

```
Browser
  │
  ▼
.htaccess ── existing file? ──► static asset (assets/)
  │
  ▼
index.php (front controller)
  │  config/local.php missing → redirect to install.php
  ▼
app/bootstrap.php
  │  autoload, helpers, config, timezone, security headers, session
  ▼
Router
  │  match method + path
  │  validate CSRF on every state-changing request
  │  run middleware (auth / guest / admin / role)
  ▼
Controller
  │  object-level scope and ownership checks
  ▼
Model / Service
  │  PDO prepared statements, transactions where required
  ▼
View (server-rendered PHP)
  │  layout + partials + escaped output
  ▼
HTML / CSV / PDF / PNG response
```

`index.php` catches `HttpException` and renders a branded error page (or JSON when requested). Unexpected exceptions are logged to `storage/logs/php-error.log` and shown as a generic 500 message unless `debug` is on.

### Lazy lifecycle refresh

Shared hosting has no cron. `QRService::refreshStatuses()` runs when evaluation screens load. It promotes scheduled sessions/assignments that have opened, and expires those past their window. No background daemon is required; state converges on the next authorized request.

## 4. Directory responsibilities

| Path | Responsibility |
|---|---|
| `index.php` | Single entry point; config guard; router dispatch; error rendering |
| `install.php` | One-time browser installer; writes secrets and imports schema |
| `app/Core/` | Framework primitives: `Router`, `Request`, `Database`, `Auth`, `Csrf`, `Session`, `Security`, `Validator`, `View`, `Setting`, `RateLimiter` |
| `app/Controllers/` | HTTP orchestration and authorization boundaries |
| `app/Models/` | Focused queries for users, surveys, evaluations, analytics, UAT, audit |
| `app/Services/` | Business rules: analytics, AI rules, predictions, QR, reports, UAT, demo data, notifications |
| `app/Views/` | Layouts, partials, role-specific pages, printable reports/posters |
| `app/Libraries/` | Vendored FPDF 1.9 + JSON font metrics |
| `assets/` | Public static files only; PHP execution denied |
| `config/` | `app.php`, `database.php`, `local.php` (secret), `local.sample.php` |
| `database/` | `schema.sql` (43 tables + settings) |
| `storage/` | Logs, cache, generated reports, `installed.lock`; denied to web |
| `documentation/` | This documentation set; denied to web |
| `tests/`, `tools/` | Development-only verification; excluded from the hosting package |

## 5. Layers and conventions

### Controllers

Controllers extend `BaseController`, which provides:

- `demo()` — current demo/live scope from `demo_mode`.
- `inScope($row)` — rejects records whose `is_demo` differs from the active scope.
- `requireAdmin()` / `audit()` / `notifyAdmins()`.
- `postString()` / `postArray()` — safe request access.
- `backWithError()` and pagination helpers.

Controllers do not trust route middleware alone. Record-level checks (for example, an instructor may open only a session whose `instructor_id` matches the authenticated user) are repeated before data is read or changed.

### Models

Models contain SQL for one domain and return arrays. They do not render output or make authorization decisions.

### Services

Services own multi-step rules that must stay consistent across controllers:

- `AnalyticsService` — institutional and instructor-scoped aggregation.
- `AIEngine` — rule-based sentiment/theme analysis and summaries.
- `PredictionService` — trend estimation and persistence.
- `RecommendationService` — evidence-linked teaching recommendations.
- `QRService` — token creation, rotation, close, revoke, archive.
- `EvaluationService` — access state, validation, anonymous submission.
- `SurveyService` — versioned survey authoring and publication.
- `UatService` — invitations, rating validation, interpretation.
- `ReportService` — report assembly and CSV/PDF export.
- `DemoService` — fictional dataset generation/reset.
- `DefaultDataService` — standard templates and UAT form.
- `AuditService` / `NotificationService` — best-effort governance side effects.

### Views

Views are plain PHP templates with a layout. Output is escaped with `e()`. Chart data is injected as JSON into a server-rendered page; no SPA framework is used. CSV and PDF responses bypass HTML layouts.

## 6. Configuration and secrets

`config/app.php` defines safe defaults. `config/local.php` overrides them and is generated by the installer or copied from `config/local.sample.php`.

Precedence:

1. Built-in defaults (`config/app.php`).
2. Local overrides (`config/local.php`).
3. Optional environment overrides for database tests/staging (`ICOTAS_DB_HOST`, `ICOTAS_DB_PORT`, `ICOTAS_DB_DATABASE`, `ICOTAS_DB_USERNAME`, `ICOTAS_DB_PASSWORD`).

The application key must be a random 64-character hex string. It protects encrypted QR/UAT tokens and HMAC-based IP hashing. Changing it invalidates re-displayable tokens; it does not destroy stored responses.

`config/local.php` is git-ignored and denied by `.htaccess`.

See `security.md` for the full control set.

## 7. Session and identity

`Session::start()` applies httponly, `SameSite=Lax`, secure cookies when HTTPS is detected, strict mode, and periodic ID regeneration. `Auth` stores only the user ID and role slug in the session; the user record is reloaded from the database on demand, so role or status changes take effect on the next request.

The login limiter has two layers:

- Per username + hashed IP: 5 failures in 15 minutes.
- Network ceiling: 100 login POSTs per 15 minutes per hashed IP, sized for campus NAT.

IP addresses are stored as HMAC-SHA-256 hashes, not plaintext.

## 8. Demo/live isolation

`system_settings.demo_mode` selects the active scope. Tables with an `is_demo` column are filtered by that scope in list, edit, analytics, and session flows. `evaluation_participation` has no `is_demo` column; its scope is derived from the parent session. `evaluation_responses` is scoped through its session.

Demo reset deletes only demo-flagged definitions and their dependent records, plus demo users. Live records survive, and the final step switches `demo_mode` off. See `demo-data.md`.

## 9. Error handling and observability

- HTTP errors: branded HTML page or JSON for clients requesting JSON.
- Application errors: logged with message, file, line, and trace; generic message shown in production.
- Audit log: authentication, academic changes, settings, QR lifecycle, reports, UAT, demo generation/reset, and other governance actions.
- Notifications: in-app only; no email/SMS transport.
- Report history: records report generation metadata for auditability.

## 10. Extensibility constraints

Keep these invariants when extending the system:

1. Never add `student_id` or a directly identifying column to `evaluation_responses` or `response_answers`.
2. Never weaken the transaction/lock around participation redemption and response insertion.
3. Never publish edits to an already-published survey version; create a new version.
4. Never claim trained-ML accuracy for `PredictionService`.
5. Never mix demo and live records in one query without an explicit scope filter.
6. Keep all runtime dependencies vendored and offline-compatible.
