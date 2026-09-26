<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

/*
 * P7-006: hardening headers + CSP modes + denial audit. The structured
 * logger gains a Monolog TestHandler so audit records are asserted
 * without touching log files.
 */

function securityTestHandler(): TestHandler
{
    $handler = new TestHandler;

    Log::channel('structured')->getLogger()->pushHandler($handler);

    return $handler;
}

function securityAuditRecords(TestHandler $handler, string $event): array
{
    return array_values(array_filter(
        $handler->getRecords(),
        fn ($record): bool => ($record['context']['security_event'] ?? null) === $event
    ));
}

it('sends the hardening header set with an enforced CSP', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
        ->assertHeader('X-Request-Id');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toBeString()
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain('report-uri /csp-report')
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('sends public responses with the same baseline', function (): void {
    $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('uses the report-only header in report-only mode', function (): void {
    config()->set('security.csp_mode', 'report-only');

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)->toBeString()->and($csp)->toContain('report-uri /csp-report');
});

it('sends HSTS only on secure or forced responses', function (): void {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    config()->set('security.headers.hsts_force', true);

    $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('audits denial statuses with correlation ids', function (): void {
    $handler = securityTestHandler();
    $user = User::factory()->create();

    // Admin-gated route as a non-admin -> 403; the headers middleware
    // records it as a denial with the request correlation id.
    $this->actingAs($user)->get('/jobs')->assertForbidden();

    $records = securityAuditRecords($handler, 'denial');

    expect($records)->not->toBeEmpty()
        ->and($records[0]['context']['status'])->toBe(403)
        ->and($records[0]['context']['http_request_id'] ?? null)->toBeString();
});

it('audits throttle hits as denials', function (): void {
    $handler = securityTestHandler();
    config()->set('security.throttles.csp_report', [1, 1]);

    $this->postJson('/csp-report', ['csp-report' => ['blocked-uri' => 'x']])->assertNoContent(204);
    $this->postJson('/csp-report', ['csp-report' => ['blocked-uri' => 'x']])->assertStatus(429);

    $records = securityAuditRecords($handler, 'denial');

    expect(collect($records)->firstWhere('context.status', 429))->not->toBeNull();
});

it('audits failed logins without credential values', function (): void {
    $handler = securityTestHandler();
    User::factory()->create(['email' => 'owner@example.com']);

    $this->post('/login', [
        '_token' => csrf_token(),
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ]);

    $records = securityAuditRecords($handler, 'auth_failure');

    expect($records)->not->toBeEmpty()
        ->and(json_encode($records[0]['context']))->not->toContain('wrong-password');
});
