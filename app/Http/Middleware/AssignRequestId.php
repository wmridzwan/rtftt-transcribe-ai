<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a per-request correlation identifier (P7-005).
 *
 * A well-formed inbound X-Request-Id is honored; otherwise a UUID is generated.
 * The id is exposed to structured logs as `http_request_id` and echoed on the
 * response so a request can be correlated across layers. Domain-level transport
 * request ids (worker invocations, ADR-017 `request_id`) remain authoritative
 * for outbound worker calls and are never overwritten by this id.
 *
 * Registered as global middleware (P7-005 corrective M-3) so the header is
 * present on every HTTP response, including unmatched routes (404), CSRF
 * failures (419), and the framework health endpoint (`/up`).
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'http_request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Log::withContext([self::ATTRIBUTE => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /**
     * The current request's correlation id, if one has been assigned.
     *
     * Used to propagate HTTP correlation explicitly into queued work. Returns
     * `null` outside an HTTP request (console/queue context).
     */
    public static function currentId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->header(self::HEADER);

        // `\z` (not `$`) so a trailing newline can never satisfy the check.
        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,128}\z/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
