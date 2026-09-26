<?php

namespace App\Http\Controllers;

use App\Security\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CSP violation report endpoint (P7-006).
 *
 * Receives browser `report-uri` POSTs in both report-only and enforced
 * modes. Public by necessity (reports carry no session), throttled
 * against log flooding, CSRF-exempt (reports are cross-context posts),
 * body-capped, and logged metadata-only to the structured channel.
 */
class CspReportController extends Controller
{
    public function store(Request $request): Response
    {
        $raw = (string) $request->getContent();

        if (strlen($raw) > 8192) {
            return response()->noContent(413);
        }

        $report = json_decode($raw, true);

        if (! is_array($report)) {
            return response()->noContent(422);
        }

        $body = is_array($report['csp-report'] ?? null) ? $report['csp-report'] : $report;

        SecurityAuditLog::record('csp_violation', [
            'document_uri' => is_string($body['document-uri'] ?? null) ? substr($body['document-uri'], 0, 512) : null,
            'violated_directive' => is_string($body['violated-directive'] ?? null) ? substr($body['violated-directive'], 0, 128) : null,
            'blocked_uri' => is_string($body['blocked-uri'] ?? null) ? substr($body['blocked-uri'], 0, 512) : null,
            'source_file' => is_string($body['source-file'] ?? null) ? substr($body['source-file'], 0, 512) : null,
        ]);

        return response()->noContent(204);
    }
}
