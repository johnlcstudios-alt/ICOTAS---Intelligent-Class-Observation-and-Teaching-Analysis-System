<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Models\Evaluation;

/**
 * Session lifecycle and QR token management. The plaintext QR token is never
 * stored; a short-lived AES-GCM ciphertext allows authorized re-display while
 * validation always uses the SHA-256 hash.
 */
final class QRService
{
    private Evaluation $evaluations;

    public function __construct()
    {
        $this->evaluations = new Evaluation();
    }

    public function refreshStatuses(?bool $demo = null): void
    {
        $suffix = $demo === null ? '' : ' AND is_demo = ' . ($demo ? '1' : '0');
        Database::execute(
            "UPDATE evaluation_sessions SET status = 'active', updated_at = NOW()
             WHERE status = 'scheduled' AND opens_at <= NOW() AND expires_at >= NOW()" . $suffix
        );
        Database::execute(
            "UPDATE evaluation_sessions SET status = 'expired', updated_at = NOW()
             WHERE status IN ('scheduled','active') AND expires_at < NOW()" . $suffix
        );
        Database::execute(
            "UPDATE evaluation_assignments SET status = 'active', updated_at = NOW()
             WHERE status = 'scheduled' AND opens_at <= NOW() AND closes_at >= NOW()" . ($demo === null ? '' : ' AND is_demo = ' . ($demo ? '1' : '0'))
        );
        Database::execute(
            "UPDATE evaluation_assignments SET status = 'closed', updated_at = NOW()
             WHERE status IN ('scheduled','active') AND closes_at < NOW()" . ($demo === null ? '' : ' AND is_demo = ' . ($demo ? '1' : '0'))
        );
    }

    public function createSession(int $assignmentId, string $opensAt, string $expiresAt, int $userId, bool $demo = false): array
    {
        $assignment = $this->evaluations->getAssignment($assignmentId);
        if (!$assignment) {
            return [false, 'Evaluation assignment not found.'];
        }
        if ((int) $assignment['is_demo'] !== ($demo ? 1 : 0)) {
            return [false, 'The assignment is outside the active data scope.'];
        }
        $opensTs = strtotime($opensAt);
        $expiresTs = strtotime($expiresAt);
        if (!$opensTs || !$expiresTs) {
            return [false, 'Enter valid opening and expiry date-times.'];
        }
        if ($expiresTs <= $opensTs) {
            return [false, 'The expiry must be after the opening time.'];
        }
        if ($expiresTs > strtotime('+30 days', $opensTs)) {
            return [false, 'A QR evaluation session may remain open for at most 30 days.'];
        }

        $token = Security::randomToken(32);
        $sessionCode = $this->uniqueCode();
        $status = 'scheduled';
        if ($opensTs <= time() && $expiresTs >= time()) {
            $status = 'active';
        } elseif ($expiresTs < time()) {
            return [false, 'The expiry time has already passed.'];
        }

        $sessionId = Database::insert(
            'INSERT INTO evaluation_sessions
             (assignment_id, session_code, token_hash, token_cipher, status, opens_at, expires_at, is_demo, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$assignmentId, $sessionCode, hash('sha256', $token), Security::encrypt($token), $status, date('Y-m-d H:i:s', $opensTs), date('Y-m-d H:i:s', $expiresTs), $demo ? 1 : 0, $userId]
        );

        $students = $this->evaluations->eligibleStudents($assignment);
        $this->evaluations->createParticipationRows($sessionId, $students, $assignment);

        return [true, 'QR evaluation session created.', ['id' => $sessionId, 'token' => $token, 'code' => $sessionCode, 'status' => $status, 'students' => count($students)]];
    }

    public function regenerate(int $sessionId, int $userId): array
    {
        $session = $this->evaluations->getSession($sessionId);
        if (!$session) {
            return [false, 'Session not found.'];
        }
        if (!in_array($this->effectiveStatus($session), ['scheduled', 'active'], true)) {
            return [false, 'Closed, expired, or archived sessions cannot be regenerated.'];
        }
        $token = Security::randomToken(32);
        Database::execute(
            'UPDATE evaluation_sessions SET token_hash = ?, token_cipher = ?, updated_at = NOW() WHERE id = ?',
            [hash('sha256', $token), Security::encrypt($token), $sessionId]
        );
        (new AuditService())->record('qr.regenerate', 'evaluation_session', $sessionId, 'Regenerated QR token for session ' . $session['session_code'], $userId);
        return [true, 'QR token regenerated. Previously printed codes no longer work.', $token];
    }

    public function close(int $sessionId, int $userId): array
    {
        $session = $this->evaluations->getSession($sessionId);
        if (!$session) {
            return [false, 'Session not found.'];
        }
        if ($session['status'] === 'archived') {
            return [false, 'Archived sessions cannot be closed.'];
        }
        Database::execute(
            'UPDATE evaluation_sessions SET status = "closed", closed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [$sessionId]
        );
        (new AuditService())->record('qr.close', 'evaluation_session', $sessionId, 'Closed QR session ' . $session['session_code'], $userId);
        return [true, 'Evaluation session closed.'];
    }

    /**
     * Permanently invalidate the current QR link and close the session. Unlike
     * close(), the old token no longer resolves at all, so a leaked or printed
     * poster cannot even reveal the closed-session page.
     */
    public function revoke(int $sessionId, int $userId): array
    {
        $session = $this->evaluations->getSession($sessionId);
        if (!$session) {
            return [false, 'Session not found.'];
        }
        if ($session['status'] === 'archived') {
            return [false, 'Archived sessions cannot be revoked.'];
        }
        $replacement = Security::randomToken(32);
        Database::execute(
            'UPDATE evaluation_sessions
             SET token_hash = ?, token_cipher = ?, status = "closed", closed_at = NOW(), updated_at = NOW()
             WHERE id = ?',
            [hash('sha256', $replacement), Security::encrypt($replacement), $sessionId]
        );
        (new AuditService())->record('qr.revoke', 'evaluation_session', $sessionId, 'Revoked QR token for session ' . $session['session_code'], $userId);
        return [true, 'QR link revoked. The previous code no longer works and the session is closed.'];
    }

    public function archive(int $sessionId, int $userId): array
    {
        Database::execute('UPDATE evaluation_sessions SET status = "archived", updated_at = NOW() WHERE id = ?', [$sessionId]);
        (new AuditService())->record('qr.archive', 'evaluation_session', $sessionId, 'Archived QR session', $userId);
        return [true, 'Session archived.'];
    }

    public function tokenForDisplay(array $session): ?string
    {
        return Security::decrypt((string) ($session['token_cipher'] ?? ''));
    }

    public function evaluationUrl(array $session): string
    {
        $token = $this->tokenForDisplay($session);
        return $token ? url('evaluate/' . $token) : '';
    }

    public function effectiveStatus(array $session): string
    {
        $status = (string) ($session['status'] ?? 'scheduled');
        if (in_array($status, ['closed', 'archived'], true)) {
            return $status;
        }
        $now = time();
        $opens = strtotime((string) $session['opens_at']);
        $expires = strtotime((string) $session['expires_at']);
        if ($expires && $now > $expires) {
            return 'expired';
        }
        if ($opens && $now < $opens) {
            return 'scheduled';
        }
        return 'active';
    }

    public function isOpen(array $session): bool
    {
        return $this->effectiveStatus($session) === 'active';
    }

    public function participationSummary(array $session): array
    {
        return $this->evaluations->sessionStats((int) $session['id']);
    }

    private function uniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = Security::randomCode(8);
            $exists = Database::scalar('SELECT COUNT(*) FROM evaluation_sessions WHERE session_code = ?', [$code], 0);
            if ((int) $exists === 0) {
                return $code;
            }
        }
        return strtoupper(substr(Security::randomToken(8), 0, 8));
    }
}
