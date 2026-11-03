<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\QRToken;
use App\Models\User;

/**
 * QR credential lifecycle.
 *
 * Security model:
 *  - 32 random bytes are generated with random_bytes().
 *  - Only the SHA-256 hash is stored.
 *  - The raw token is shown once, immediately after generation, so the Dean
 *    can render/print the QR image. It can never be recovered from the DB.
 *  - The token alone never exposes scores: the faculty member must be signed
 *    in, and ownership is checked against the authenticated account.
 */
final class QRCodeService
{
    public function generate(int $facultyId, array $dean, ?int $expiryDays = null): array
    {
        $faculty = User::findFaculty($facultyId);
        if ($faculty === null) {
            throw new \RuntimeException('The selected user is not a faculty member.');
        }
        if (($faculty['account_status'] ?? '') !== 'Active') {
            throw new \RuntimeException('QR credentials can only be issued to active faculty accounts.');
        }

        $expiryDays = $expiryDays ?? (int) setting('qr_expiry_days', 365);
        $expiryDays = max(1, min(3650, $expiryDays));

        $raw = bin2hex(random_bytes((int) config('security.qr.token_bytes', 32)));
        $hash = hash('sha256', $raw);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiryDays . ' days'));

        $tokenId = \App\Core\Database::transaction(function () use ($facultyId, $hash, $expiresAt, $dean): int {
            // Only one active credential per faculty: revoke previous ones.
            QRToken::revokeAllForFaculty($facultyId);
            $id = QRToken::createToken($facultyId, $hash, $expiresAt, (int) $dean['user_id']);

            AuditLog::record(
                (int) $dean['user_id'],
                'qr.generated',
                'QR Management',
                'faculty:' . $facultyId,
                'New QR credential issued (token #' . $id . '), expires ' . $expiresAt . '.'
            );

            return $id;
        });

        return [
            'token_id'   => $tokenId,
            'raw_token'  => $raw,
            'expires_at' => $expiresAt,
            'payload'    => $this->payloadFor($raw),
            'faculty'    => $faculty,
        ];
    }

    public function revoke(int $facultyId, array $dean): bool
    {
        $faculty = User::findFaculty($facultyId);
        if ($faculty === null) {
            throw new \RuntimeException('Faculty member not found.');
        }

        $revoked = false;
        \App\Core\Database::transaction(function () use ($facultyId, $dean, &$revoked): void {
            $active = QRToken::activeForFaculty($facultyId);
            if ($active !== null) {
                $revoked = QRToken::revoke((int) $active['token_id'], $facultyId);
            }
            AuditLog::record(
                (int) $dean['user_id'],
                'qr.revoked',
                'QR Management',
                'faculty:' . $facultyId,
                $revoked ? 'Active QR credential revoked.' : 'Revoke requested; no active credential was present.'
            );
        });

        return $revoked;
    }

    /**
     * Verify a scanned token. Never returns evaluation data.
     *
     * @return array{ok:bool,reason:string,message:string,record:?array,faculty:?array}
     */
    public function verify(string $rawToken): array
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
            return $this->failure('invalid', 'This QR credential is not valid.');
        }

        $hash = hash('sha256', strtolower($rawToken));
        $record = QRToken::findByHash($hash);
        if ($record === null) {
            return $this->failure('invalid', 'This QR credential is not recognised.');
        }

        if (($record['token_status'] ?? '') === 'Revoked') {
            return $this->failure('revoked', 'This QR credential has been revoked. Ask the Dean to issue a new one.');
        }

        if (($record['token_status'] ?? '') === 'Expired' || strtotime((string) $record['expires_at']) <= time()) {
            if (($record['token_status'] ?? '') === 'Active') {
                QRToken::updateById((int) $record['token_id'], ['token_status' => 'Expired']);
            }
            return $this->failure('expired', 'This QR credential has expired. Ask the Dean to issue a new one.');
        }

        if (($record['token_status'] ?? '') !== 'Active') {
            return $this->failure('invalid', 'This QR credential is no longer active.');
        }

        if (($record['account_status'] ?? '') !== 'Active' || ($record['role'] ?? '') !== 'Faculty') {
            return $this->failure('inactive', 'The faculty account linked to this credential is not active.');
        }

        QRToken::touchUsed((int) $record['token_id']);

        return [
            'ok'      => true,
            'reason'  => 'valid',
            'message' => 'QR credential verified.',
            'record'  => $record,
            'faculty' => [
                'user_id'        => (int) $record['faculty_id'],
                'full_name'      => (string) $record['full_name'],
                'email'          => (string) $record['email'],
                'department_id'  => $record['department_id'],
            ],
        ];
    }

    /**
     * Database-backed verification throttle to slow token guessing.
     */
    public function attemptsExceeded(?string $ip = null): bool
    {
        $minutes = max(1, (int) config('security.qr.verification_window_minutes', 15));
        $max = max(1, (int) config('security.qr.max_verification_attempts', 10));
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);

        $stmt = \App\Core\Database::connection()->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action IN ('qr.verify_failed', 'qr.verify_denied')
               AND created_at >= ? AND (ip_address = ? OR ? IS NULL)"
        );
        $stmt->execute([$since, $ip ?? \App\Core\Request::ip(), null]);
        return ((int) $stmt->fetchColumn()) >= $max;
    }

    public function recordFailure(string $reason, ?int $userId = null): void
    {
        AuditLog::record($userId, 'qr.verify_failed', 'QR Verification', 'qr', 'Verification failed: ' . $reason);
    }

    public function recordSuccess(int $facultyId, int $tokenId): void
    {
        AuditLog::record($facultyId, 'qr.verify_success', 'QR Verification', 'token:' . $tokenId, 'Faculty credentials verified.');
    }

    public function payloadFor(string $rawToken): string
    {
        return url('/qr/access?token=' . $rawToken);
    }

    private function failure(string $reason, string $message): array
    {
        return [
            'ok'      => false,
            'reason'  => $reason,
            'message' => $message,
            'record'  => null,
            'faculty' => null,
        ];
    }
}
