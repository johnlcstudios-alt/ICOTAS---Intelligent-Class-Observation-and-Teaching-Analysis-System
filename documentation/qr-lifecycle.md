# QR Session Lifecycle and Token Security

## 1. Purpose

A QR session makes one published survey version available to an eligible class for a bounded window. Students can enter through:

1. A camera scan of the QR code (jsQR, browser camera permission).
2. A direct evaluation link.
3. An 8-character manual session code.

All three paths converge on `/evaluate/{token}`, which requires an authenticated student and an eligible, open session.

## 2. Token design

| Property | Implementation |
|---|---|
| Token generation | `random_bytes(32)` → 64 hexadecimal characters |
| Validation lookup | SHA-256 hash in `evaluation_sessions.token_hash` (unique) |
| Re-display | AES-256-GCM ciphertext in `token_cipher`, key derived from the application key |
| Manual code | 8 characters from `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no ambiguous 0/O/1/I) |
| URL exposure | Token only appears in the evaluation URL; no student identity is included |
| Storage | Plaintext token is never stored; only hash + encrypted copy |

Validation always uses the hash. The encrypted copy exists so an authorized dean can re-display or print the code. Changing the application key makes old ciphertext unreadable; the session can still be validated by hash, but the code cannot be re-displayed.

## 3. States

```text
                 create in future
                        │
                        ▼
                   scheduled ──────────────┐
                        │ opens_at reached│
                        ▼                  │ expires_at passed
                     active ───────────────┤
                        │                  ▼
                        │              expired
                        │
          close / revoke│
                        ▼
                     closed
                        │
                  archive│
                        ▼
                    archived
```

Status is refreshed lazily when evaluation screens load (no cron). Effective access also computes the window on the fly, so a stale status cannot open an expired session.

| State | Student access | Regenerate | Close | Revoke | Archive |
|---|---|---|---|---|---|
| `scheduled` | Not yet open | ✅ | ✅ | ✅ | ✅ |
| `active` | ✅ if eligible | ✅ | ✅ | ✅ | ✅ |
| `expired` | No | ❌ | ✅ | ✅ | ✅ |
| `closed` | No | ❌ | — | ✅ | ✅ |
| `archived` | No | ❌ | ❌ | ❌ | — |

The UI exposes only actions valid for the current state. The service/controller re-checks state on every POST.

## 4. Actions

### Create

- Requires an existing assignment in the same demo/live scope.
- Opening and expiry must parse, expiry must be after opening, and the window may last at most 30 days.
- A session may start immediately (`active`) or in the future (`scheduled`).
- Participation rows are created for the assignment's eligible students.
- Audit action: `qr.create`.

### Regenerate

- Allowed only for `scheduled` or `active` sessions.
- Rotates both `token_hash` and `token_cipher`.
- Every previously printed poster/link stops resolving (lookup by old hash fails).
- Audit action: `qr.regenerate`.

### Close

- Sets `closed` and `closed_at`.
- The existing token still resolves, but the evaluation page reports that the session is closed.
- Participation and responses remain intact for reporting.
- Audit action: `qr.close`.

### Revoke

- Intended for a leaked or misprinted code.
- Rotates the token to a new random value, closing the old link completely (404), and sets `closed`.
- Archived sessions cannot be revoked.
- Audit action: `qr.revoke`.
- Automated and HTTP tests verify the old link returns 404 after revocation.

### Archive

- Sets `archived`; no student access.
- Keeps historical reporting data.
- Audit action: `qr.archive`.

## 5. Display and print

- The session page renders the QR client-side with qrcodejs from `assets/vendor/qrcode.min.js`.
- A PNG download link is produced from the QR canvas by `assets/js/qr-poster.js`.
- The print poster is a server-rendered print layout at `/dean/sessions/{id}/poster` with the institution name, session details, QR, direct URL, and manual code.
- Only an authorized dean/academic head in the correct scope may open the session, poster, or download controls.
- The QR and URL contain the secret token. Treat printed posters as sensitive until the session closes. Revoke and reprint if a poster is exposed to an unintended audience.

## 6. Student entry paths

| Path | Flow |
|---|---|
| QR scan | Browser camera reads the URL; the student must already be signed in; router resolves the token |
| Direct link | Same `/evaluate/{token}` route; requires authenticated student |
| Manual code | `/student/join` uppercases the code, resolves the session, checks demo/live scope and eligibility, then redirects to the token URL |
| Portal quick-open | `/student/open/{id}`; can be disabled with `allow_student_self_scan` |

Direct and QR paths also require the session's `is_demo` scope to match the student's `is_demo` flag.

## 7. Access checks

Before rendering the form, `EvaluationService::accessState()` checks:

1. Effective status: scheduled, active, expired, closed, archived.
2. Student eligibility for the assignment's course/section/year/semester.
3. Existing participation: `submitted` → duplicate guard.
4. Only `available` renders the questionnaire.

The POST path repeats all checks and re-validates answers server-side. A crafted POST cannot submit after close, without eligibility, or twice.

## 8. One response per session

The participation table has `UNIQUE (evaluation_session_id, student_id)`. Submission:

- Inserts a pending row if absent.
- Locks it with `SELECT ... FOR UPDATE`.
- Rejects if already submitted.
- Inserts one response and its answers.
- Updates the row to `submitted` only if still pending.
- Marks the eligibility token used.

Any failure rolls back the transaction. The duplicate guard is visible when the link is reopened.

## 9. Security checklist

- [x] Cryptographic token, not a sequential identifier.
- [x] Hash-only lookup at rest.
- [x] Encrypted re-display copy.
- [x] Token rotation invalidates printed codes.
- [x] Revoke makes the old link 404.
- [x] Session window capped at 30 days.
- [x] CSRF required on every lifecycle action.
- [x] Server-side role and scope checks.
- [x] Audit trail for create/regenerate/close/revoke/archive.
- [x] Duplicate submission blocked transactionally.
- [ ] Rate limiting specifically on token guesses — the manual code space is large (32^8), and login is throttled, but there is no separate per-token brute-force counter. Add one before high-risk public deployment if threat modeling requires it.
