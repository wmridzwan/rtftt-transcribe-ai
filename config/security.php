<?php

return [

    /*
     * Security hardening baseline (P7-006, D7-08 single-admin posture).
     * All values are enforced in every environment where the relevant
     * surface runs; production-only fail-closed behavior is documented
     * per key. Key names are registered in ProductionEnvRegistry.
     */

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        // Sent only on secure responses (or when forced); see SecurityHeaders.
        'hsts' => 'max-age=31536000; includeSubDomains',
        'hsts_force' => env('HSTS_FORCE', false),
    ],

    /*
     * CSP mode: "report-only" collects violations at /csp-report without
     * blocking; "enforce" blocks. Production target is "enforce"; the
     * report-only → enforce transition is evidenced per task AC1.
     */
    'csp_mode' => env('CSP_MODE', 'enforce'),
    'csp_report_uri' => '/csp-report',

    /*
     * Named rate limiters [max attempts, decay minutes]. Single-admin
     * model: login stays at Fortify's 5/min default semantics; upload
     * initiation is generous; the CSP report endpoint is throttled to
     * prevent log flooding from unauthenticated callers.
     */
    'throttles' => [
        'login' => [(int) env('LOGIN_THROTTLE_MAX', 5), 1],
        'upload_initiate' => [(int) env('UPLOAD_THROTTLE_MAX', 30), 1],
        'csp_report' => [(int) env('CSP_REPORT_THROTTLE_MAX', 60), 1],
    ],

    /*
     * Upload-abuse limits (TD-002 pairing side): per-user count cap per
     * hour and byte cap per rolling 24h day. Rejection is fail-closed
     * (HTTP 429) with no partial ingestion residue.
     */
    'abuse' => [
        'max_uploads_per_hour' => (int) env('UPLOAD_ABUSE_MAX_PER_HOUR', 60),
        'max_bytes_per_day' => (int) env('UPLOAD_ABUSE_MAX_BYTES_PER_DAY', 10_737_418_240),
    ],

    /*
     * Self-hosted ClamAV (D7-04). Daemon socket preferred; TCP loopback
     * fallback. Non-loopback TCP endpoints are refused unless explicitly
     * allowed (no third-party media egress, ever).
     */
    'clamav' => [
        'enabled' => (bool) env('CLAMAV_ENABLED', false),
        'socket' => env('CLAMAV_SOCKET'),
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'allow_non_loopback' => (bool) env('CLAMAV_ALLOW_NON_LOOPBACK', false),
        'timeout_seconds' => (int) env('CLAMAV_TIMEOUT_SECONDS', 60),
        'max_signature_age_hours' => (int) env('CLAMAV_MAX_SIGNATURE_AGE_HOURS', 48),
        // hold|reject when the scanner is unavailable (fail closed either way).
        'unavailable_mode' => env('CLAMAV_UNAVAILABLE_MODE', 'reject'),
        'quarantine_directory' => 'quarantine',
    ],

];
