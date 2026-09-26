<?php

namespace App\Deployment;

/**
 * Upload-limit adequacy audit (P7-001, TD-002 application share).
 *
 * Compares the effective PHP receiving limits against the approved 500 MiB
 * (524,288,000-byte) product boundary plus multipart/proxy overhead. The
 * required floor is 600 MiB, matching the P7-008 deployment directives
 * (runbook §7); the boundary math is documented here, not in a comment
 * elsewhere:
 *
 *   500 MiB product boundary
 * + ~100 MiB multipart encoding / proxy buffering / timeout headroom
 * = 600 MiB (629,145,600 bytes) minimum effective limit.
 *
 * Pure logic with injectable ini values so tests never depend on the
 * machine's php.ini. Exit-code behavior belongs to the `env:audit-limits`
 * command; this class only evaluates.
 */
final class UploadLimitAudit
{
    /** Minimum effective receiving limit: 600 MiB in bytes. */
    public const REQUIRED_BYTES = 629_145_600;

    /** Approved per-file product boundary: exactly 500 MiB in bytes. */
    public const PRODUCT_BOUNDARY_BYTES = 524_288_000;

    /**
     * Parse a php.ini size string ("512M", "1G", "2048K", "-1", "4096")
     * into bytes. "-1" (unlimited) maps to PHP_INT_MAX.
     */
    public static function iniBytes(string $directive, ?string $raw = null): int
    {
        $raw ??= (string) ini_get($directive);
        $raw = trim($raw);

        if ($raw === '' || $raw === '-1') {
            return PHP_INT_MAX;
        }

        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        $unit = strtolower(substr($raw, -1));
        $number = (float) substr($raw, 0, -1);

        $multiplier = match ($unit) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        return max(0, (int) ($number * $multiplier));
    }

    /**
     * @param  array{upload_max_filesize?: string, post_max_size?: string}|null  $ini
     * @return array{upload_max_filesize: int, post_max_size: int, required: int, pass: bool, findings: list<string>}
     */
    public static function evaluate(?array $ini = null): array
    {
        $uploadMax = self::iniBytes('upload_max_filesize', $ini['upload_max_filesize'] ?? null);
        $postMax = self::iniBytes('post_max_size', $ini['post_max_size'] ?? null);
        $findings = [];

        if ($uploadMax < self::REQUIRED_BYTES) {
            $findings[] = sprintf(
                'upload_max_filesize (%s) is below the required %s; files at the 500 MiB product boundary would be rejected before Laravel validation.',
                self::formatBytes($uploadMax),
                self::formatBytes(self::REQUIRED_BYTES)
            );
        }

        if ($postMax < self::REQUIRED_BYTES) {
            $findings[] = sprintf(
                'post_max_size (%s) is below the required %s; multipart uploads at the boundary would be truncated before validation.',
                self::formatBytes($postMax),
                self::formatBytes(self::REQUIRED_BYTES)
            );
        }

        return [
            'upload_max_filesize' => $uploadMax,
            'post_max_size' => $postMax,
            'required' => self::REQUIRED_BYTES,
            'pass' => $findings === [],
            'findings' => $findings,
        ];
    }

    public static function formatBytes(int|float $bytes): string
    {
        if ($bytes === PHP_INT_MAX) {
            return 'unlimited';
        }

        if ($bytes >= 1024 * 1024 * 1024) {
            return sprintf('%.1fG', $bytes / (1024 * 1024 * 1024));
        }

        if ($bytes >= 1024 * 1024) {
            return sprintf('%.0fM', $bytes / (1024 * 1024));
        }

        if ($bytes >= 1024) {
            return sprintf('%.0fK', $bytes / 1024);
        }

        return $bytes.'B';
    }
}
