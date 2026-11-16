<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use InvalidArgumentException;
use RuntimeException;

/**
 * Hashed, single-purpose registration invitations.
 *
 * Codes are shown once when created and stored only as SHA-256 hashes. A
 * redemption locks the row inside the caller's transaction so two concurrent
 * sign-ups cannot consume the same single-use code.
 */
final class InviteService
{
    public const CODE_PREFIX = 'DEAN';
    public const MAX_USES_LIMIT = 25;
    public const MAX_VALID_DAYS = 90;

    /**
     * Create an invitation and return the plaintext code exactly once.
     */
    public function create(string $roleSlug, int $maxUses = 1, int $validDays = 7, string $note = '', ?int $createdBy = null): array
    {
        $roleId = Database::scalar('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$roleSlug]);
        if ($roleId === null) {
            throw new InvalidArgumentException('Unknown role for invitation.');
        }

        $maxUses = max(1, min(self::MAX_USES_LIMIT, $maxUses));
        $validDays = max(1, min(self::MAX_VALID_DAYS, $validDays));
        $raw = Security::randomCode(12);
        $code = self::format($raw);
        $expiresAt = date('Y-m-d H:i:s', time() + ($validDays * 86400));
        $note = Security::cleanText($note, 190, false);

        $id = Database::insert(
            'INSERT INTO registration_invites
             (role_id, code_hash, code_hint, note, max_uses, used_count, expires_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())',
            [
                (int) $roleId,
                self::hash($code),
                self::hint($raw),
                $note !== '' ? $note : null,
                $maxUses,
                $expiresAt,
                $createdBy,
            ]
        );

        return [
            'id' => $id,
            'code' => $code,
            'role' => $roleSlug,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Lock an invitation row for the duration of the current transaction.
     * Throws a generic exception for every invalid state so callers do not
     * reveal whether a code exists, expired, or was revoked.
     */
    public function lockRedeemable(string $code, string $roleSlug): array
    {
        $hash = self::hash($code);
        if ($hash === '') {
            throw new InvalidArgumentException('Invalid invitation code.');
        }

        $invite = Database::fetch(
            'SELECT i.*, r.slug AS role
             FROM registration_invites i
             JOIN roles r ON r.id = i.role_id
             WHERE i.code_hash = ? LIMIT 1 FOR UPDATE',
            [$hash]
        );
        if (!$invite || (string) $invite['role'] !== $roleSlug) {
            throw new InvalidArgumentException('Invalid invitation code.');
        }
        if ($invite['revoked_at'] !== null) {
            throw new InvalidArgumentException('Invalid invitation code.');
        }
        if ($invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) < time()) {
            throw new InvalidArgumentException('Invalid invitation code.');
        }
        if ((int) $invite['used_count'] >= (int) $invite['max_uses']) {
            throw new InvalidArgumentException('Invalid invitation code.');
        }
        return $invite;
    }

    public function consume(int $inviteId): void
    {
        $updated = Database::execute(
            'UPDATE registration_invites
             SET used_count = used_count + 1, updated_at = NOW()
             WHERE id = ? AND used_count < max_uses AND revoked_at IS NULL',
            [$inviteId]
        );
        if ($updated !== 1) {
            throw new RuntimeException('The invitation is no longer available.');
        }
    }

    public function revoke(int $inviteId): bool
    {
        return Database::execute(
            'UPDATE registration_invites SET revoked_at = NOW(), updated_at = NOW()
             WHERE id = ? AND revoked_at IS NULL',
            [$inviteId]
        ) > 0;
    }

    public function all(int $limit = 100): array
    {
        $limit = max(1, min(300, $limit));
        return Database::fetchAll(
            'SELECT i.*, r.slug AS role, r.name AS role_name,
                    u.username AS created_by_username
             FROM registration_invites i
             JOIN roles r ON r.id = i.role_id
             LEFT JOIN users u ON u.id = i.created_by
             ORDER BY i.id DESC
             LIMIT ' . $limit
        );
    }

    public function find(int $inviteId): ?array
    {
        return Database::fetch('SELECT * FROM registration_invites WHERE id = ? LIMIT 1', [$inviteId]);
    }

    public static function status(array $invite): string
    {
        if ($invite['revoked_at'] !== null) {
            return 'revoked';
        }
        if ($invite['expires_at'] !== null && strtotime((string) $invite['expires_at']) < time()) {
            return 'expired';
        }
        if ((int) $invite['used_count'] >= (int) $invite['max_uses']) {
            return 'used';
        }
        return 'active';
    }

    /**
     * Normalise user input to the 12-character code body. The display prefix
     * and separator dashes are accepted but ignored.
     */
    public static function normalize(string $code): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($value) === 16 && str_starts_with($value, self::CODE_PREFIX)) {
            $value = substr($value, strlen(self::CODE_PREFIX));
        }
        return $value;
    }

    public static function hash(string $code): string
    {
        $normalized = self::normalize($code);
        return $normalized === '' ? '' : hash('sha256', $normalized);
    }

    public static function format(string $raw): string
    {
        $raw = self::normalize($raw);
        if (strlen($raw) !== 12) {
            return self::CODE_PREFIX . '-' . $raw;
        }
        return self::CODE_PREFIX . '-' . substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
    }

    public static function hint(string $raw): string
    {
        $raw = self::normalize($raw);
        return self::CODE_PREFIX . '-' . substr($raw, 0, 4) . '…';
    }
}
