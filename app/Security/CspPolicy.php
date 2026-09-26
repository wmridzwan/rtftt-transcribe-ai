<?php

namespace App\Security;

/**
 * Content-security policy builder (P7-006).
 *
 * Default-deny with a justified allowlist for the verified Chromium
 * workspace (P4/P6/P7-010 behavior):
 *
 * - `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: Alpine.js directives,
 *   Flux/Livewire bootstrapping, and the upload-progress XHR script are
 *   inline by framework design, and Alpine evaluates component expressions
 *   via `new Function` (proven by report-only evidence: EvalError without
 *   it — see verification/artifacts/p7-006-csp-report-only.json). No
 *   external script source is loaded anywhere (verified: views reference
 *   only outbound links and SVG namespaces).
 * - `style-src 'self' 'unsafe-inline'`: Tailwind/Flux inline styles.
 * - `img-src 'self' data:`: inline SVG/data-uri icons.
 * - `media-src 'self'`: authorized same-origin stream/download endpoints.
 * - `connect-src 'self'` (+ dev websocket allowance when debug is on, so
 *   Vite HMR keeps working in local development only).
 * - `frame-ancestors 'none'`: the workspace is never framed (matches
 *   X-Frame-Options DENY).
 * - `form-action 'self'`, `base-uri 'self'`, `object-src 'none'`.
 *
 * Violations always report to the `csp-report` endpoint, in both modes.
 */
final class CspPolicy
{
    public static function directives(bool $reportOnly = false): string
    {
        $connect = ["'self'"];

        if ((bool) config('app.debug', false)) {
            $connect[] = 'ws:';
            $connect[] = 'wss:';
        }

        $parts = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "media-src 'self'",
            'connect-src '.implode(' ', $connect),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            'report-uri '.(string) config('security.csp_report_uri', '/csp-report'),
        ];

        return implode('; ', $parts);
    }
}
