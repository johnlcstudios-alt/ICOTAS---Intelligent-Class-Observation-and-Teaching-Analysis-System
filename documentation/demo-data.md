# Demonstration Data

## 1. Purpose

The demo dataset exists to show the interface and analytics without exposing real student or faculty data. Every record is fictional and flagged. It is **not** research evidence, a pilot result, or a substitute for UAT with real participants.

## 2. Generation

### From the interface

Dean → **Demo Data** → **Generate demonstration data**.

### From the command line

```powershell
C:\xampp\php\php.exe tools\seed_demo.php generate
C:\xampp\php\php.exe tools\seed_demo.php status
C:\xampp\php\php.exe tools\seed_demo.php reset
```

`DemoService::seed()` first removes any existing demo records, then rebuilds the dataset in one transaction, computes predictions and recommendations, sets `demo_mode = 1`, and records an audit entry. Running generate twice does not duplicate demo records.

## 3. Baseline contents

| Entity | Count |
|---|---:|
| Departments | 4 |
| Programs | 6 |
| Courses | 12 |
| Sections | 9 |
| Instructors | 12 |
| Students | 90 |
| Evaluation sessions | 84 |
| Evaluation responses | 729 |
| UAT respondents | 8 |

Additional seeded definitions include 7 survey templates, 1 demo survey/version, participation rows, class schedules, teaching loads, observations, recommendations, predictions, and notifications. Counts can change after interacting with demo sessions; use the Demo Data page for current counts.

## 4. Accounts

All demo accounts use password **`Demo@12345`**.

| Role | Username pattern | Count |
|---|---|---:|
| Dean | `demo.dean` | 1 |
| Academic Head | `demo.head.cba`, `demo.head.ccs` | 2 |
| Instructor | `demo.instructor.01` … `demo.instructor.12` | 12 |
| Student | `demo.student.001` … `demo.student.090` | 90 |

These are fictional names and credentials. Do not reuse the demo password for real accounts.

## 5. Isolation model

- `users.is_demo = 1` separates demo accounts from real accounts.
- Thirteen definition tables carry `is_demo` (see `database.md`).
- `evaluation_participation` is scoped through its session.
- `evaluation_responses` is scoped through its session.
- `system_settings.demo_mode` selects the active scope.
- List, edit, analytics, prediction, report, QR, and student flows filter by the active scope.
- Login of a demo account in live mode is blocked by scope checks on records; demo accounts remain identifiable by `is_demo`.
- Demo records cannot appear in live analytics or live reports.

## 6. Reset behavior

`DemoService::reset()` deletes, in dependency order:

- Report history requested by demo users.
- Demo UAT surveys (cascades to questions, respondents, responses, answers).
- Demo class observations.
- Demo evaluation sessions (cascades to participation, responses, answers, eligibility, sentiment, themes).
- Demo assignments, surveys, schedules, teaching loads, enrollments, courses, sections, programs, departments.
- Demo users.
- Notifications of type `demo`.

It then sets `demo_mode = 0`. Live records are not touched.

The reset is transaction-wrapped. A failure rolls back rather than leaving a partial demo dataset.

## 7. Safety rules

1. Generate demo data only on a demonstration or training deployment, or after a backup.
2. Do not enable demo mode while entering real evaluation data; the scope switch hides live records from the UI.
3. Reset demo data before handing the system to real users.
4. Never cite demo analytics, demo UAT means, or demo predictions as study results.
5. Do not use the demo password for real accounts.
6. Demo accounts can be deleted individually through User Management, but reset is the intended cleanup path.
7. `tools/seed_demo.php reset` also sets demo mode off.

## 8. What demo data demonstrates

- A complete academic hierarchy and teaching load.
- QR sessions across multiple academic periods and statuses.
- Anonymous response distribution, sentiment, themes, and category averages.
- Prediction sufficiency and insufficiency across instructors.
- Recommendations, observations, reports, and dashboard charts.
- UAT with fictional respondents and per-characteristic results.

It does not demonstrate real-world response rates, real class sizes, real sentiment accuracy, or validated prediction accuracy.
