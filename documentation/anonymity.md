# Anonymity Model

## 1. Guarantee claimed

ICOTAS claims **structural separation at the database level**: a submitted student evaluation cannot be joined to a student through the schema.

- `evaluation_responses` contains no `student_id`, name, email, or student number.
- `response_answers` contains no student identifier.
- `evaluation_participation` contains `student_id` and submission status, but no answer data.
- Both tables share only `evaluation_session_id`.
- Written feedback is stored in `response_answers` and analyzed without a student key.

This is stronger than hiding identifiers in the UI. There is no column or foreign key that directly links an answer row to a person.

## 2. Two-table design

```
evaluation_sessions
  id, token_hash, status, opens_at, expires_at, ...
        │
        ├── evaluation_participation
        │     evaluation_session_id, student_id, status, submitted_at
        │     UNIQUE (evaluation_session_id, student_id)
        │
        └── evaluation_responses
              evaluation_session_id, survey_version_id, submitted_at (hour bucket),
              submitted_on, response_status, sentiment_status, source
                    │
                    └── response_answers
                          response_id, question_id, answer_value, numeric_value
```

The participation row answers “may this student submit, and have they?”. The response row answers “what was submitted?”. The two are never joined in application queries that display answers.

## 3. Submission transaction

`EvaluationService::submit()` performs these steps inside one database transaction:

1. Re-check the session state and eligibility.
2. `INSERT IGNORE` a pending participation row if one does not exist.
3. `SELECT ... FOR UPDATE` that participation row.
4. Reject if it is already `submitted`.
5. Insert `evaluation_responses` with an **hour-bucketed** `submitted_at` (minutes/seconds set to zero).
6. Insert all `response_answers`.
7. Update participation to `submitted` only if it is still `pending`.
8. Mark the eligibility token used.

The row lock makes two simultaneous submissions from the same account resolve to one response. The second transaction sees `submitted` and returns the duplicate guard. The automated and HTTP tests verify that duplicate access adds no extra response.

## 4. Why hour bucketing

Exact submission timestamps can correlate an anonymous response with the only student seen submitting at that second. `submitted_at` is truncated to the hour; `submitted_on` keeps the date for reporting. This reduces, but does not mathematically eliminate, timing correlation when a session has very few participants.

## 5. Access control around comments

- Written comments are returned only through authorized dean/instructor views.
- Instructor views require the session's `instructor_id` to match the authenticated instructor.
- Comments are hidden unless the session has at least `privacy_min_responses` (default 3) and `privacy_show_comments` is enabled.
- AI insights require at least the privacy threshold before showing comment-derived narratives.
- No endpoint returns a student identity with a comment.

## 6. What the system does not claim

Anonymity has limits that must be disclosed:

| Risk | Status / mitigation |
|---|---|
| Small cohorts | A single response in a small class can still be inferred. The privacy threshold suppresses comment display, but aggregate scores may still be sensitive. Use institutional policy. |
| Distinctive free text | A comment can identify its author through content. The form warns students not to include names or identifying details; this is not technically enforceable. |
| Timing correlation | Hour bucketing reduces precision; it does not guarantee k-anonymity. |
| Participation list | Administrators can see who is eligible and who submitted, but not which response they wrote. |
| Database administrators | A DBA with direct SQL access can see participation and responses, but still lacks a direct key. They could attempt timing/correlation attacks. Hosting providers are outside the application's trust boundary. |
| Backups and logs | Backups contain both tables. Protect them at the same level as the live database. |
| UAT | UAT is **identified** by design; do not describe UAT responses as anonymous student evaluations. |

Do not claim “mathematically anonymous” or “GDPR-compliant by design.” The accurate claim is **“structurally separated, with no direct student identifier in response tables and reduced timing precision.”**

## 7. Data minimization

- Login and audit IP addresses are HMAC-SHA-256 hashes, not plaintext.
- `evaluation_responses` stores only what reporting needs: session, survey version, bucketed time, date, status, source.
- Response excerpts stored for AI analysis are the written comment text; they contain no student key.
- Demo data is fictional and flagged.

## 8. Verification evidence

- Schema inspection: no `student_id` in `evaluation_responses` or `response_answers`.
- Automated test: structural anonymity assertion.
- Automated test: one response + one participation row per submission.
- Automated test: duplicate submission rejected.
- HTTP test: submission changes response count by exactly one; reopening the link shows the duplicate guard; duplicate access adds zero responses.

## 9. Operational rules

1. Never add a student identifier to a response table “for convenience.”
2. Never expose a join of participation and responses through an API, export, or report.
3. Keep the privacy threshold at 3 or higher for real classes.
4. Disable comment display for very small cohorts if inference risk is unacceptable.
5. Restrict database and backup access to authorized personnel.
6. Train faculty not to attempt re-identification from free text.
7. Include an informed-notice statement in the student portal before real deployment.
8. Review data-protection obligations with the institution before processing real data.
