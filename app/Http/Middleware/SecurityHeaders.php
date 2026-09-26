<?php

namespace App\Http\Middleware;

use App\Security\CspPolicy;
use App\Security\SecurityAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security hardening headers + audit hook (P7-006).
 *
 * Applies the baseline header set and the content-security policy on every
 * response, and records authentication/authorization/rate-limit denials
 * (401/403/419/429) to the security audit log with correlation fields.
 * Composes with (never replaces) the auth/ownership stack; registered
 * globally after request-id assignment so denials carry correlation ids.
 */
class SecurityHeaders
{
    /** @var list<int> statuses recorded as security denials */
    public const DENIAL_STATUSES = [401, 403, 419, 429];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = config('security.headers', []);

        foreach ([
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
            'Permissions-Policy',
        ] as $header) {
            if (isset($headers[$header]) && is_string($headers[$header])) {
                $response->headers->set($header, $headers[$header]);
            }
        }

        if (($request->isSecure() || (bool) ($headers['hsts_force'] ?? false)) && isset($headers['hsts'])) {
            $response->headers->set('Strict-Transport-Security', (string) $headers['hsts']);
        }

        $mode = (string) config('security.csp_mode', 'enforce');
        $policy = CspPolicy::directives($mode === 'report-only');
        $name = $mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
        $response->headers->set($name, $policy);

        if (in_array($response->getStatusCode(), self::DENIAL_STATUSES, true)) {
            SecurityAuditLog::denial($request, $response->getStatusCode());
        }

        return $response;
    }

    public static function currentRequestId(): ?string
    {
        return AssignRequestId::currentId();
    }
}
