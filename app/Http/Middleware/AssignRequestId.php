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
 * The id is exposed to structured logs and echoed on the response so a request
 * can be correlated across layers. Domain-level transport request ids (worker
 * invocations) remain authoritative for outbound worker calls and are unchanged.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Log::withContext([self::ATTRIBUTE => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->header(self::HEADER);

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
