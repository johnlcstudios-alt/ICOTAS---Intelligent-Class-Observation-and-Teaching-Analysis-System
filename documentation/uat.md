# User Acceptance Testing (ISO/IEC 25010)

## 1. Purpose and scope

ICOTAS includes a built-in UAT module for evaluating the system itself against selected ISO/IEC 25010 product-quality characteristics. UAT is separate from the anonymous student course-evaluation flow:

- UAT respondents are **identified and invitation-controlled**.
- UAT does **not** feed student evaluation analytics, sentiment, predictions, or faculty reports.
- UAT results evaluate the software, not an instructor.

## 2. Fixed interpretation thresholds

The following thresholds are hard-coded in `UatService::interpret()` and documented in the UAT screens and reports. Do not change them per survey.

| Mean range | Interpretation |
|---|---|
| 4.50 – 5.00 | Very Satisfactory |
| 3.50 – 4.49 | Satisfactory |
| 2.50 – 3.49 | Fair |
| 1.50 – 2.49 | Poor |
| 1.00 – 1.49 | Very Poor |

**Boundary rule:** 4.48 is **Satisfactory**, not Very Satisfactory. The system and its tests enforce this exact interpretation.

## 3. Standard instrument

The default form contains six characteristics and three statements per characteristic (18 rating items), using a 1–5 scale:

1. Functional Suitability
2. Usability
3. Performance Efficiency
4. Reliability
5. Security
6. Maintainability

Respondent types are free text with common values: IT Professional, Faculty, Student, Academic Head.

## 4. Workflow

1. **Create** a UAT survey (`/dean/uat/create`); the standard questions are seeded automatically.
2. **Review/edit** questions on the survey page.
3. **Add respondents** with name and type. Each receives a single-use invitation token.
4. **Set dates** and change status to `active`.
5. **Distribute the invitation link** (`/uat/respond/{token}`) manually. The application does not send email.
6. **Respondent rates** each statement 1–5. Submission is transaction-locked and single-use.
7. **Review results** per characteristic and overall: N, mean, population standard deviation, interpretation.
8. **Export** the UAT report as CSV or PDF.

Invitation tokens are 24 random bytes, stored as SHA-256 plus an AES-GCM ciphertext for authorized re-display. Invitations can be invalid, not yet open, closed, or already submitted.

## 5. Results presentation

Per characteristic:

- N (ratings)
- Mean (2 decimals)
- Population standard deviation
- Interpretation using the fixed table

Overall mean is the mean of all ratings, not the mean of characteristic means. Reports show the scale and the 4.48 boundary note.

### Reference results are never merged

`uat_reference_results` can hold previously reported research values with a `source_label` and notes. If populated, the UAT page and report show them in a separate table labeled **“Previously reported research reference results (not new UAT data).”** They are never combined with new responses or counted in N.

At the time of this build the table is **empty** because the original manuscript was not available in the repository. Populate it only from a verified source, for example:

```sql
INSERT INTO uat_reference_results
  (characteristic, mean_score, sd_score, interpretation, source_label, notes)
VALUES
  ('Usability', 4.48, NULL, 'Satisfactory', 'Author (year), Table X', 'Reported value; not new UAT data.');
```

Do not invent a source label or a standard deviation.

## 6. Current results

### Synthetic demonstration data (not research evidence)

`DemoService` creates a UAT survey titled “ISO/IEC 25010 UAT — Demonstration Dataset” with 8 fictional respondents. Ratings are randomized around target values, so exact aggregates change on each reset.

One prepared test run (8 synthetic respondents, 18 items each) produced:

| Characteristic | Mean | SD (population) | Interpretation |
|---|---:|---:|---|
| Functional Suitability | 4.00 | 0.00 | Satisfactory |
| Usability | 4.33 | 0.47 | Satisfactory |
| Performance Efficiency | 4.21 | 0.41 | Satisfactory |
| Reliability | 4.21 | 0.41 | Satisfactory |
| Security | 4.38 | 0.48 | Satisfactory |
| Maintainability | 4.04 | 0.20 | Satisfactory |
| **Overall** | **4.19** | — | **Satisfactory** |

These values demonstrate that the module computes and interprets results; they are **not** the study's UAT findings and must not be cited as such.

### Existing reported research results

The research manuscript was not present in the repository. No previously reported UAT values are asserted here. If the manuscript reports values such as a 4.48 mean, that value must be entered in `uat_reference_results` with its exact source and shown separately. A 4.48 mean is **Satisfactory** under the required scale.

### New implementation results

The implementation result is functional UAT support: survey creation, invitations, single-use submission, per-characteristic descriptive statistics, exact threshold interpretation, and labeled reference separation. The automated suite verifies thresholds and submission behavior. This is a software-verification result, not a human-participant study.

## 7. UAT claims checklist

- [ ] State the number of human respondents and their recruitment.
- [ ] State the instrument and the 1–5 anchors.
- [ ] State that 4.50 is the Very Satisfactory floor and 4.48 is Satisfactory.
- [ ] Keep synthetic demo values out of the findings table.
- [ ] Keep manuscript reference values out of the new-results table.
- [ ] Report means and SDs to two decimals.
- [ ] Explain that UAT respondents are identified; UAT is not the anonymous student evaluation.
- [ ] Do not claim statistical generalization from a small convenience sample.
- [ ] Include the ISO/IEC 25010 characteristic definitions used.
