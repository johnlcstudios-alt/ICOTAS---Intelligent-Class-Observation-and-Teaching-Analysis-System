# Research Alignment and Claim Boundaries

## 1. Source limitation

The original research manuscript (`.docx`) was **not present in the repository** when this documentation was prepared. The implementation was aligned to the requirements captured in the project brief and the approved application scope.

Consequences:

- No manuscript-specific numeric result is asserted here.
- The `uat_reference_results` table is intentionally empty until a verified source is entered.
- Any statement about “the study's findings” must be reconciled with the manuscript before inclusion in a thesis, paper, or defense presentation.
- This document distinguishes implementation evidence from research evidence. It does not substitute one for the other.

## 2. Terminology policy

Use these terms precisely:

| Term | Use for | Do not use for |
|---|---|---|
| **Existing reported research value** | A result attributed to the original study with a source | New test output, demo data |
| **New implementation result** | What this codebase and its local tests produced | Research findings |
| **Synthetic demonstration data** | `DemoService` records flagged `is_demo` | Pilot or UAT evidence |
| **Unvalidated estimate** | Rule-based prediction output | ML accuracy or validated forecast |
| **Rule-Based AI-Assisted Sentiment and Theme Analysis** | `AIEngine` (`rule-v1.1`) | Trained model, LLM, external AI |
| **Rule-based trend estimate** | `PredictionService` | Machine-learning prediction |

## 3. Requirement-to-implementation map

| Research / system requirement | Implementation | Verification | Claim status |
|---|---|---|---|
| Dean/Academic Head administration | `dean`/`academic_head` routes and controllers | Route smoke, E2E RBAC | New implementation result |
| Instructor private data only | Ownership checks; instructor-scoped analytics/predictions/reports | Automated instructor-scope test; RBAC review | New implementation result |
| Student mobile portal with QR, link, code | `/student/scan`, `/student/join`, `/evaluate/{token}` | E2E manual code, direct link, submission | New implementation result |
| One response per session | Unique participation row + transaction + row lock | Automated duplicate test; E2E duplicate guard | New implementation result |
| Anonymity: no student ID in response tables | Separate participation/response schema; no student key | Structural test; schema review | New implementation result |
| Hashed, encrypted, revocable QR tokens | SHA-256 lookup + AES-256-GCM cipher + rotate/revoke | Automated and E2E revoke tests | New implementation result |
| QR states, poster, PNG, print | Lifecycle service + views + qrcodejs | Local render; print/PNG not pixel-verified | New implementation result, partial verification |
| Rule-based AI sentiment + themes | `AIEngine` `rule-v1.1`, mixed sentiment | Automated sentiment/mixed/Filipino tests | New implementation result |
| Transparent prediction, no fake accuracy | Weighted MA + least-squares slope + sufficiency gate | Automated slope/WMA/insufficiency tests | New implementation result |
| Analytics and recommendations | `AnalyticsService`, `RecommendationService`, evidence-linked outputs | Route smoke, automated analytics tests | New implementation result |
| Reports and exports | CSV/PDF with labeled scale/source | E2E CSV/PDF checks | New implementation result |
| UAT with fixed thresholds | `UatService`, `uat_*` tables, UAT report | Automated threshold/submission tests | New implementation result |
| 4.48 is Satisfactory | Exact `interpret()` boundaries + UI note | Automated threshold test | New implementation result |
| Demo data isolated and resettable | `is_demo`, `demo_mode`, reset service | Automated live isolation; E2E reset | New implementation result (synthetic data) |
| Installer and manual fallback | `install.php`, local sample, schema import | Installer test 16/16 | New implementation result |
| Fixed `htdocs` root and source protection | Front controller + `.htaccess` layers | Local Apache checks | New implementation result; host untested |

## 4. What the evidence supports

The local evidence supports statements such as:

- “The system enforces server-side role and ownership checks on the tested routes.”
- “Anonymous responses are structurally separated from participation records in the schema.”
- “The sentiment component is a deterministic rule-based engine, and mixed sentiment is explicitly labeled.”
- “The prediction component uses a weighted moving average and least-squares slope and refuses to produce a risk label below its sufficiency threshold.”
- “The UAT interpretation implements the approved boundaries exactly, including 4.48 = Satisfactory.”
- “Local automated, HTTP, and installer checks passed as recorded in `testing.md`.”

## 5. What the evidence does not support

Do not claim:

- That the system has been validated on InfinityFree.
- That the sentiment engine has a measured accuracy, precision, recall, or F1 score.
- That predictions have validated ML accuracy or are causal.
- That the demo dataset represents real ACLC classes or real response rates.
- That the synthetic UAT means are the study's UAT findings.
- That the system is fully anonymous against timing, small-cohort, or database-administrator inference.
- That it is WCAG-certified, penetration-tested, or production-load-tested.
- That every route and browser/device combination has been exercised.
- That the original manuscript's results have been reproduced.

## 6. UAT threshold reporting

The approved scale is:

| Mean | Interpretation |
|---|---|
| 4.50 – 5.00 | Very Satisfactory |
| 3.50 – 4.49 | Satisfactory |
| 2.50 – 3.49 | Fair |
| 1.50 – 2.49 | Poor |
| 1.00 – 1.49 | Very Poor |

A mean of **4.48 is Satisfactory**. This is enforced in code, tests, the UAT screen, and reports.

When the manuscript is available:

1. Extract each reported mean, SD, N, and characteristic.
2. Enter them in `uat_reference_results` with the exact source label.
3. Keep them in a separate table from new UAT responses.
4. Do not merge, average, or re-interpret them.
5. Reconcile any discrepancy with the approved threshold table explicitly.

## 7. Suggested manuscript wording

### AI component

> The system implements a Rule-Based AI-Assisted Sentiment and Theme Analysis component (engine `rule-v1.1`). It uses a deterministic sentiment lexicon, phrase rules, negation and intensifier handling, and keyword-based theme mapping. It is not a trained language model, and no accuracy metric is claimed. Mixed sentiment is retained as a distinct category rather than averaged into neutrality.

### Prediction component

> Trend indicators are rule-based estimates derived from a weighted moving average of recent period means and a least-squares linear slope. The system suppresses output when the configured minimum periods or responses are not met. The output is not a validated machine-learning prediction and must be interpreted with academic judgment.

### UAT

> User acceptance was interpreted on a five-point scale: 4.50–5.00 Very Satisfactory, 3.50–4.49 Satisfactory, 2.50–3.49 Fair, 1.50–2.49 Poor, and 1.00–1.49 Very Poor. A mean of 4.48 is therefore Satisfactory. Previously reported values and new UAT responses are reported separately.

### Anonymity

> Student responses are stored without a student identifier. Eligibility and completion are recorded in a separate participation table, and submission is transaction-locked to prevent duplicates. Response timestamps are hour-bucketed. The design reduces direct and timing-based identification but does not claim mathematical anonymity, particularly in very small cohorts or where free text reveals identity.

## 8. Future work

1. Reconcile the manuscript and insert verified reference UAT values.
2. Conduct a human-labeled sentiment validation study and report accuracy/precision/recall only if measured.
3. Validate predictions against later observed periods; report calibration/error if justified.
4. Conduct a real UAT with documented recruitment and sample size.
5. Test on InfinityFree and record PHP/MySQL versions, installer result, and smoke evidence.
6. Perform accessibility review (WCAG 2.2 AA target) and physical-device QR testing.
7. Add department-scoped Academic Head access if required.
8. Add per-token brute-force throttling and MFA for administrative accounts.
9. Add CSV formula-injection neutralization if exports are opened in spreadsheets.
10. Conduct an independent security review and backup/restore drill.

## 9. Documentation discipline

When updating this project:

- Add new evidence to `testing.md`, not to marketing copy.
- Label synthetic results at the point of display.
- Keep the AI and prediction labels verbatim.
- Do not let a demo screenshot become a figure caption without the synthetic-data label.
- Re-run the full verification suite after code changes and update the recorded counts.
