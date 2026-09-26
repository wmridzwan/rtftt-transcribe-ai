<?php

namespace App\Security;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Security-event audit log (P7-006, AC6).
 *
 * Writes verdicts and metadata — never media bytes, secrets, or raw
 * request bodies — to the P7-005 structured channel with correlation
 * fields. Retention of audit entries follows the application log
 * retention; D7-06 purge interaction is owned by P7-011.
 */
final class SecurityAuditLog
{
    /** @param array<string, mixed> $context */
    public static function record(string $event, array $context = []): void
    {
        $context['security_event'] = $event;

        $requestId = AssignRequestId::currentId();

        if ($requestId !== null) {
            $context['http_request_id'] = $requestId;
        }

        Log::channel('structured')->info('security.'.$event, self::redact($context));
    }

    public static function denial(Request $request, int $status): void
    {
        self::record('denial', [
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);
    }

    /** @param array<string, mixed> $verdict */
    public static function scanVerdict(array $verdict): void
    {
        self::record('scan_verdict', [
            'verdict' => $verdict['verdict'] ?? 'unknown',
            'engine' => $verdict['engine'] ?? null,
            'signature_date' => $verdict['signature_date'] ?? null,
            'sha256' => $verdict['sha256'] ?? null,
            'size_bytes' => $verdict['size_bytes'] ?? null,
        ]);
    }

    public static function guardRefusal(string $guard, string $summary): void
    {
        // Key names and counts only — values never enter the log.
        self::record('guard_refusal', ['guard' => $guard, 'summary' => $summary]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function redact(array $context): array
    {
        unset($context['token'], $context['password'], $context['secret'], $context['body']);

        return $context;
    }
}
