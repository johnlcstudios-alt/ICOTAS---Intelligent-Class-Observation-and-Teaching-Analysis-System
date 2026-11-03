# ICOTAS — Intelligent Classroom Observation and Teaching Analysis System

A native PHP 8.x classroom observation system for ACLC College Tacloban.

## What it does

- Dean and Faculty roles only (no Evaluator role).
- Administrators (Dean) create observations, assign reviewers, score the official 20-item COF, and publish results.
- Faculty view only their own published results, performance trends, and a QR-based classroom presence verification flow.
- Predictive analytics: PHP-based Simple Linear Regression over historical published scores, with MAE/RMSE validation and a minimum of 5 distinct periods. No fabricated data.

## Requirements

- PHP 8.x
- MySQL / MariaDB (InnoDB)
- Apache (public/ is the web root) or PHP built-in dev server pointed at public/
- Sessions enabled
- `public/assets/vendor/html5-qrcode.min.js` for the QR scanner (optional; manual token entry works without it)
- `public/assets/vendor/qrcode.min.js` for dean QR preview rendering
- Chart.js vendored for trend charts

## Roles

- **Dean**: full administration, observation workflow, analytics, predictions, reports, audit, QR credential management, system settings.
- **Faculty**: own published results, trends, profile, and QR credential verification only.

## COF

Official 20-item Classroom Observation Form used in this system:

- 13 Teaching Competence items
- 7 Personality items
- Each item rated 1–5

Scoring is implemented in PHP (`ScoringService`). The seeded wording is currently marked **PROVISIONAL**: the 20-item draft is not yet verified against the official ACLC Classroom Observation Form (Version 1, Series 2012). Verification is recorded through the COF management page and reflected on the COF management index.

## Security

- Server-side authorization on every routed page; dean-only routes are protected with `AuthorizationService::requireDean()`.
- No hardcoded stats; no fake data.
- Prepared statements for database access.
- CSRF tokens on state-changing forms.
- QR tokens are generated as secure random values; only SHA-256 hashes are stored.
- Ownership and authentication enforced for QR verification, with rate limiting.
- Output escaping via the project helpers (`e()`, etc.).
- Security headers and CSP-friendly client code (no inline handlers, no eval).

## Project structure

- `app/` — controllers, models, services, core, views
- `database/schema.sql` — full DB schema
- `public/` — web root; assets and entry point
- `routes/web.php` — app routing
- `.env` — environment config (copy from `.env.example`)

## Installation

1. Copy `.env.example` to `.env` and fill in the database and app settings.
2. Create the database from `database/schema.sql`.
3. Run `php tools/install.php` to prepare storage directories and generate the one-time setup token.
4. Visit `/setup` to create the initial Dean account.

## Scanning assets

The scanner JS already POSTs to `/qr/verify` and redirects on success. The dean QR preview is rendered by `initQrPreview()` in `public/assets/js/app.js`.

## Smoke-test order

1. `/setup`
2. Create Dean account
3. Login
4. Dean dashboard
5. Faculty dashboard (separate account)
6. QR scanner verify
7. Verify redirect to `/faculty/evaluations?verified=1`

## Two-role enforcement

Dean-only routes should be inaccessible to a Faculty account. Faculty accounts should only see their own published results.
