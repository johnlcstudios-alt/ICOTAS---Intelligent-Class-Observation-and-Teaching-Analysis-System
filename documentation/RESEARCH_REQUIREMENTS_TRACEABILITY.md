# Research Requirements Traceability

**System:** ICOTAS — Intelligent Class Observation & Teaching Analytics System with QR-Code Monitoring & Predictive Insights
**Institution:** ACLC College Tacloban City
**Prepared:** 2026-11-17

This table maps each of the ten research objectives to the system module that implements it, its status, the verification method used, and the evidence produced by **this** implementation.

## Claim labels used

| Label | Meaning |
|---|---|
| **Implemented + verified** | Code exists and an automated/recorded check covers the behaviour. |
| **Implemented + partial** | Code exists and renders/works, but verification is incomplete (for example only a route smoke test, or no physical-device test). |
| **Implemented, test gap** | Code exists and works in the UI, but no automated check covers it yet. |
| **Not done** | Nothing to verify. |

**Important:** the original research manuscript (`.docx`) was not present in the repository. No manuscript-specific numeric result is reproduced, asserted, or guessed here. Previously reported research values, new implementation results, synthetic demo data, and unvalidated estimates are kept distinct, as required by `research-alignment.md`. The technology stack in the manuscript (Django/Vue/PostgreSQL in the abstract; Flask/SQLAlchemy in the conceptual framework) does **not** describe this implementation: ICOTAS is implemented in **native PHP 8 / MySQL (PDO) with Bootstrap 5 and vanilla ES6 JavaScript**, because the deployment target is InfinityFree shared hosting. That deviation must be documented in the manuscript rather than implied away.

---

## 1. Objective traceability

| # | Research objective | Requirement | System module | Status | Verification method | Evidence / test case |
|---|---|---|---|---|---|---|
| 1 | Develop a flexible survey builder for the Dean | Configurable questionnaires; 8 question types; required flags; rating labels; reorder; templates; preview; versioning; submitted versions immutable | `SurveyController`, `SurveyService`, `survey*`/`question_options` tables, `assets/js/survey-builder.js`, `app/Views/dean/surveys/*` | Implemented + verified | 5 automated survey tests; route smoke | `tests/survey_test.php`: every question type stored in submitted order with one-based sequential indices; option slugging; reverse reorder persists without duplication or loss; five malformed definitions refused with the draft left intact; published version immutable to edits and re-publication; new version copies the definition and draft edits leave the published wording byte-for-byte unchanged; duplication copies the published (not draft) definition; template cloning produces an editable draft. Smoke: all survey routes HTTP 200. |
| 2 | Dynamic QR-code generation for evaluation sessions | Secure per-session token; hashed storage; generate/display/download/print/regenerate/revoke; validity window; lifecycle states | `QRService`, `SessionController`, `evaluation_sessions`, `assets/vendor/qrcode.min.js`, `assets/js/qr-scanner.js` | Implemented + verified (display/print partially) | 7 automated QR tests; E2E QR lifecycle checks; smoke | `tests/qr_test.php`: hash + cipher storage, regeneration invalidates the old printed code, close/archive stop access, time validation, revoke makes the old link unresolvable, scheduled activation. E2E: regenerate/close/archive/revoke flashes and database state. PNG download and print rendering are not pixel-verified. |
| 3 | Enforce one-response-per-session integrity | Eligibility token, participation row, unique constraints, transaction-safe redemption, no student key in responses | `EvaluationService`, `evaluation_eligibility_tokens`, `evaluation_participation`, `evaluation_responses`, `response_answers` | Implemented + verified | 4 automated evaluation tests; E2E duplicate guard | `tests/evaluation_test.php`: no direct student identifier in response tables; one response written and participation marked separately; second submission rejected; written feedback analysed without a student link. E2E: duplicate reopen shows the guard and writes no extra response. |
| 4 | Provide real-time evaluation dashboards | Dean dashboard statistics computed from records; participation rate; trends; attention list; filters | `AnalyticsService`, `Analytics` model, `DashboardController`/`AnalyticsController`, Chart.js views | Implemented + verified | 5 automated analytics tests; smoke crawl | `tests/analytics_test.php`: dean dashboard totals reconcile with source rows; participation rate derived from eligible/submitted; category means on the 1–5 scale; live scope isolated from demo. Smoke: `/dean/dashboard`, `/dean/analytics` HTTP 200. |
| 5 | AI-assisted sentiment analysis and thematic classification | Rule-based sentiment (positive/neutral/negative/mixed), 10 teaching themes, executive summary, transparent labelling | `AIEngine` (`rule-v1.1`), `sentiment_results`, `feedback_themes`, `AiController` | Implemented + verified (no accuracy claim) | 6 automated AI tests | `tests/ai_test.php`: positive, negative, mixed (not averaged away), neutral/empty safety, Filipino sentiment terms, engine version transparency. **No** precision/recall/F1 is claimed. |
| 6 | Predictive instructor satisfaction indicators | Rule-based risk indicators; moving average; least-squares trend; sufficiency thresholds; recorded method and metrics | `PredictionService`, `prediction_results`, `prediction_configurations`, `PredictionController` | Implemented + verified | 5 automated prediction tests | `tests/prediction_test.php`: exact slope for a straight line; weighted moving average favours recent periods; insufficient history produces an explicit insufficiency result instead of a forecast; thresholds sourced from configuration; stored results carry method and supporting metrics. Output is explicitly an unvalidated estimate. |
| 7 | Private instructor dashboards | Instructors see only their own sessions, analytics, predictions, recommendations, observations, reports; no student identity | `InstructorController`, ownership filters in analytics/prediction/recommendation/report services, `Auth::requireRole('instructor')` | Implemented + verified | Automated instructor-scope test; E2E RBAC; smoke | `tests/analytics_test.php`: instructor analytics restricted to that instructor. E2E: student 403 on dean/instructor areas; instructor reaches only own portal. Report type allowlist re-checked on export. |
| 8 | Actionable pedagogical recommendations | Concern → supporting metric/theme → suggested improvement → reason; Dean review; instructor tracking | `RecommendationService`, `instructor_recommendations`, `RecommendationController` | Implemented + verified | 4 automated recommendation tests; route smoke | `tests/recommendations_test.php`: every teaching theme resolves to a complete playbook; every stored row carries theme, concern, recommendation, reason, priority, `source = rule_based`, and a supporting metric that is either a negative-mention count or a category average **below 3.50** with a non-zero rating count; regeneration refreshes open rows instead of duplicating them (one open row per theme, second run creates zero); another instructor cannot see or change a recommendation; the owner and an administrator can change status while an unknown status is refused. |
| 9 | Institutional reporting and data exports | 12 report types; CSV, PDF, printable HTML; branding; privacy-safe instructor-facing output | `ReportService`, `app/Libraries/fpdf.php`, `ReportController`, `report_history` | Implemented + verified | E2E export checks; smoke | E2E: CSV export returns 200 with `text/csv` and populated body; PDF export returns 200, starts with `%PDF`, non-trivial size. Instructor-facing report allowlist enforced server-side. |
| 10 | Evaluate the system against ISO/IEC 25010 characteristics | Separate UAT module; six characteristics; configurable Likert instruments; weighted mean; SD; composite mean; exact interpretation thresholds; export | `UatService`, `uat_surveys`/`uat_questions`/`uat_responses`, `UatController`, UAT report | Implemented + verified | 3 automated UAT tests; E2E UAT flows | `tests/uat_test.php`: interpretation follows the approved threshold table exactly (4.48 = Satisfactory); survey creation/invitation/submission transaction-safe; malformed ratings rejected before any write. Previously reported research means are **not** mixed into new UAT responses. |

---

## 2. ISO/IEC 25010 characteristics selected by the research

The research selected six characteristics. This table states honestly what evidence exists for each; it is **not** a claim of ISO/IEC 25010 certification.

| Characteristic | Selected by research | Evidence in this implementation | Status |
|---|---|---|---|
| Functional suitability | Yes | 49 automated tests, 171 HTTP end-to-end checks, route smoke for all four roles, installer test 17/17 | Verified locally |
| Usability | Yes | Role-specific navigation and dashboards, light/dark themes, empty states, mobile-first student portal; UAT instrument for interaction capability | Implemented; no WCAG audit performed |
| Performance efficiency | Yes | Indexed schema, aggregate SQL, pagination, 15-row admin pages, lazy/bounded analytics, no cron or polling; local suite runs in seconds | Locally adequate; **no load benchmark** |
| Reliability | Yes | PDO transactions for submission/invitation/demo seeding, unique constraints, duplicate guards, audit logging, safe archival states | Verified by automated + E2E checks |
| Security | Yes | Server-side RBAC on every protected route, `password_hash`/`password_verify`, CSRF on all POSTs, prepared statements everywhere, session hardening, hashed tokens, rate limiting, forced password change, privilege-escalation guards | Verified by tests + security review; **no penetration test** |
| Maintainability | Yes | MVC-inspired structure, services for domain logic, layered docs, 49-test suite, migration SQL, no external runtime dependencies | Verified by structure review and passing suite |

Additional engineering checks performed outside the research selection: compatibility (MySQL/MariaDB, no vendor-specific SQL), flexibility (configurable thresholds, themes, survey templates), and deployment fit for the InfinityFree free plan.

---

## 3. Hosting-constraint traceability

| Constraint | Implementation | Verified |
|---|---|---|
| No Python, Django, or Flask on the host | Analytics, sentiment, theme, and prediction logic is native PHP | Yes — no Python files or subprocess calls exist |
| No Node.js, npm, or build step on the host | Bootstrap, Chart.js, jsQR, and qrcode.js are vendored under `assets/vendor/` | Yes — assets served statically |
| No cron, queue, or long-running process | Analytics/predictions are computed on request; housekeeping runs probabilistically on login | Yes — code review |
| No WebSocket server | Notifications are stored in MySQL and rendered per request | Yes |
| No paid AI API | `AIEngine` is rule-based and local; external AI is disabled by default | Yes |
| No PostgreSQL | MySQL/MariaDB syntax only; schema imports through phpMyAdmin | Yes — schema imported into MariaDB 10.4.32 |
| Fixed `htdocs` document root | Front controller plus layered `.htaccess` denies for `app/`, `config/`, `database/`, `documentation/`, `storage/`, `tools/`, `tests/` | Locally; host enforcement **not yet tested** |
| HTTPS | Session cookies set `secure` when HTTPS is detected | Locally; host certificate **not yet tested** |

---

## 4. Known gaps that must not be glossed over

1. InfinityFree deployment has not been performed, so host compatibility is not established. Local Apache `.htaccess` enforcement **has** been verified (11/11 requests), but that is not the host's configuration.
2. The CSV user-import path has no automated test (import validation is covered by code review and manual runs).
3. No accuracy metric exists for the rule-based sentiment engine.
4. Predictions are unvalidated estimates with configurable thresholds.
5. UAT reference values from the manuscript are not entered because the manuscript is absent; `uat_reference_results` is intentionally empty.
6. No MFA, no per-token brute-force throttle, no department-scoped Academic Head access.
7. Browser camera QR scanning, poster printing, WCAG, load, and penetration testing remain unperformed.

See `DEVELOPMENT_PROGRESS.md` for the live status of each item.
