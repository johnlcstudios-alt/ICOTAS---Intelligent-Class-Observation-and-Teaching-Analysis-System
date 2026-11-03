<?php

namespace App\Models;

/**
 * Faculty QR credentials. Only SHA-256 token hashes are ever persisted.
 */
final class QRToken extends BaseModel
{
    protected static string $table = 'faculty_qr_tokens';
    protected static string $primaryKey = 'token_id';

    public static function activeForFaculty(int $facultyId): ?array
    {
        return static::fetchOne(
            "SELECT * FROM faculty_qr_tokens
             WHERE faculty_id = ? AND token_status = 'Active'
             ORDER BY token_id DESC LIMIT 1",
            [$facultyId]
        );
    }

    public static function findByHash(string $hash): ?array
    {
        return static::fetchOne(
            'SELECT t.*, u.full_name, u.email, u.account_status, u.role, u.department_id
             FROM faculty_qr_tokens t
             JOIN users u ON u.user_id = t.faculty_id
             WHERE t.token_hash = ? LIMIT 1',
            [$hash]
        );
    }

    public static function forFaculty(int $facultyId): array
    {
        return static::fetchAll(
            'SELECT * FROM faculty_qr_tokens WHERE faculty_id = ? ORDER BY token_id DESC',
            [$facultyId]
        );
    }

    public static function allWithFaculty(): array
    {
        return static::fetchAll(
            "SELECT t.*, u.full_name AS faculty_name, u.employee_no, u.account_status,
                    d.department_name, d.department_code
             FROM faculty_qr_tokens t
             JOIN users u ON u.user_id = t.faculty_id
             LEFT JOIN departments d ON d.department_id = u.department_id
             ORDER BY t.issued_at DESC, t.token_id DESC"
        );
    }

    public static function createToken(int $facultyId, string $hash, string $expiresAt, int $createdBy): int
    {
        return static::insert([
            'faculty_id'  => $facultyId,
            'token_hash'  => $hash,
            'issued_at'   => date('Y-m-d H:i:s'),
            'expires_at'  => $expiresAt,
            'token_status'=> 'Active',
            'created_by'  => $createdBy,
        ]);
    }

    public static function revokeAllForFaculty(int $facultyId): void
    {
        $stmt = static::pdo()->prepare(
            "UPDATE faculty_qr_tokens
             SET token_status = 'Revoked', revoked_at = ?
             WHERE faculty_id = ? AND token_status = 'Active'"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $facultyId]);
    }

    public static function revoke(int $tokenId, int $facultyId): bool
    {
        $stmt = static::pdo()->prepare(
            "UPDATE faculty_qr_tokens
             SET token_status = 'Revoked', revoked_at = ?
             WHERE token_id = ? AND faculty_id = ? AND token_status = 'Active'"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $tokenId, $facultyId]);
        return $stmt->rowCount() > 0;
    }

    public static function touchUsed(int $tokenId): void
    {
        $stmt = static::pdo()->prepare(
            'UPDATE faculty_qr_tokens SET last_used_at = ? WHERE token_id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $tokenId]);
    }

    public static function expireOutdated(): int
    {
        $stmt = static::pdo()->prepare(
            "UPDATE faculty_qr_tokens
             SET token_status = 'Expired'
             WHERE token_status = 'Active' AND expires_at <= ?"
        );
        $stmt->execute([date('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }

    public static function countActive(): int
    {
        return (int) static::scalar("SELECT COUNT(*) FROM faculty_qr_tokens WHERE token_status = 'Active'");
    }
}
