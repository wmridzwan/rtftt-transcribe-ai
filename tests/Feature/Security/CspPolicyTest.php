<?php

use App\Security\CspPolicy;

/*
 * P7-006: CSP directive allowlist. Every source is justified in
 * CspPolicy; this test pins the reviewed allowlist so future edits
 * are deliberate, never silent widening.
 */

it('pins the reviewed script and style allowlist', function (): void {
    config()->set('app.debug', false);

    $directives = CspPolicy::directives();

    // unsafe-eval is an Alpine.js/Livewire framework requirement, proven
    // by report-only EvalError evidence (see CspPolicy docblock).
    expect($directives)->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->and($directives)->toContain("style-src 'self' 'unsafe-inline'")
        ->and($directives)->toContain("frame-ancestors 'none'")
        ->and($directives)->toContain("object-src 'none'")
        ->and($directives)->toContain('report-uri /csp-report');
});

it('loads no external script, style, font, or connect source', function (): void {
    config()->set('app.debug', false);

    $directives = CspPolicy::directives();

    expect($directives)->not->toContain('https://')
        ->and($directives)->not->toContain('http://')
        ->and($directives)->not->toContain('*');
});

it('allows dev websocket channels only when debug is on', function (): void {
    config()->set('app.debug', true);

    expect(CspPolicy::directives())->toContain('ws:');

    config()->set('app.debug', false);

    expect(CspPolicy::directives())->not->toContain('ws:');
});
