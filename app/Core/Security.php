<?php

namespace App\Core;

/**
 * Cryptographic and input-safety helpers used across the application.
 */
final class Security
{
    public static function appKey(): string
    {
        return (string) config('app.key', 'insecure');
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function randomCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        return $code;
    }

    public static function hashIp(string $ip = ''): string
    {
        $ip = $ip !== '' ? $ip : Request::ip();
        return hash_hmac('sha256', $ip, self::appKey());
    }

    public static function encrypt(string $plaintext): string
    {
        $key = hash('sha256', self::appKey(), true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt secure token.');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        if (strpos($payload, 'v1:') !== 0) {
            return null;
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $key = hash('sha256', self::appKey(), true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    public static function cleanText(?string $value, int $maxLength = 5000, bool $multiline = true): string
    {
        $value = (string) $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if (!$multiline) {
            $value = preg_replace('/\s+/', ' ', $value) ?? '';
        }
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    public static function passwordProblem(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one letter and one number.';
        }
        return null;
    }

    /**
     * Validate an uploaded image and return its extension. SVG is intentionally
     * refused because it can carry executable script when served.
     */
    public static function validateImageUpload(array $file, int $maxBytes): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [false, 'No image was uploaded, or the upload failed.'];
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return [false, 'The image exceeds the ' . round($maxBytes / 1048576, 1) . ' MB limit.'];
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name'] ?? '');
        if (!isset($allowed[$mime])) {
            return [false, 'Only JPG, PNG, WEBP or GIF images are accepted.'];
        }
        return [true, $allowed[$mime]];
    }

    public static function safeFileName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
        return substr($name, 0, 180);
    }

    /**
     * Whether a free-text comment may be displayed without exposing identity.
     * Emails, phone numbers, social handles and long ID-like numbers are scrubbed.
     */
    public static function anonymizeComment(string $comment): string
    {
        $comment = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email removed]', $comment) ?? $comment;
        $comment = preg_replace('/(?:\+63|0)9\d{9}\b/', '[phone removed]', $comment) ?? $comment;
        $comment = preg_replace('/\b\d{7,}\b/', '[number removed]', $comment) ?? $comment;
        $comment = preg_replace('/@[A-Za-z0-9_\.]{3,}/', '[handle removed]', $comment) ?? $comment;
        return trim($comment);
    }

    public static function containsPii(string $comment): bool
    {
        return (bool) preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|(?:\+63|0)9\d{9}\b|\b\d{7,}\b/i', $comment);
    }

    public static function csrfHeaderName(): string
    {
        return 'X-CSRF-Token';
    }
}
