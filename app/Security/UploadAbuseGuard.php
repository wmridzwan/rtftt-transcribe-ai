<?php

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Upload-abuse limits (P7-006, TD-002 pairing side).
 *
 * Per-user count cap per rolling hour and byte cap per rolling 24h day,
 * enforced before any ingestion work begins — so rejection provably
 * leaves no partial media/transcription state. Exceeding either cap
 * fails closed with HTTP 429 and a clear message; both caps are
 * documented single-admin values, adjustable via config.
 */
final class UploadAbuseGuard
{
    public static function check(User $user, int $fileSizeBytes): void
    {
        $maxUploads = max(1, (int) config('security.abuse.max_uploads_per_hour', 60));
        $maxBytes = max(1, (int) config('security.abuse.max_bytes_per_day', 10_737_418_240));

        $hourKey = sprintf('upload-abuse:count:%d:%s', $user->id, now()->format('YmdH'));
        $dayKey = sprintf('upload-abuse:bytes:%d:%s', $user->id, now()->format('Ymd'));

        $count = (int) Cache::get($hourKey, 0);
        $bytes = (int) Cache::get($dayKey, 0);

        if ($count >= $maxUploads) {
            SecurityAuditLog::record('abuse_limit', [
                'limit' => 'uploads_per_hour', 'user_id' => $user->id, 'count' => $count,
            ]);

            throw new HttpException(429, sprintf(
                'Upload limit exceeded: at most %d uploads per hour. Please retry later.',
                $maxUploads
            ));
        }

        if ($bytes + $fileSizeBytes > $maxBytes) {
            SecurityAuditLog::record('abuse_limit', [
                'limit' => 'bytes_per_day', 'user_id' => $user->id, 'bytes' => $bytes,
            ]);

            throw new HttpException(429, 'Upload limit exceeded: daily upload volume exhausted. Please retry later.');
        }

        Cache::put($hourKey, $count + 1, now()->addHour());
        Cache::put($dayKey, $bytes + $fileSizeBytes, now()->addDay());
    }
}
