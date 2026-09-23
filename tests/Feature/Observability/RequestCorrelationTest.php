<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

it('generates and echoes a correlation id when none is supplied', function () {
    Log::spy();

    $response = app(AssignRequestId::class)->handle(
        Request::create('/', 'GET'),
        fn () => new Response('ok'),
    );

    $id = $response->headers->get('X-Request-Id');

    expect($id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
    Log::shouldHaveReceived('withContext')->once()->with(['http_request_id' => $id]);
})->group('p7-005');

it('honors a well-formed inbound correlation id', function () {
    Log::spy();

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', 'trace-abcdef123456');

    $response = app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Request-Id'))->toBe('trace-abcdef123456');
    Log::shouldHaveReceived('withContext')->once()->with(['http_request_id' => 'trace-abcdef123456']);
})->group('p7-005');

it('replaces a malformed inbound correlation id', function () {
    Log::spy();

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', 'short');

    $response = app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Request-Id'))
        ->not->toBe('short')
        ->toMatch('/^[0-9a-f-]{36}$/');
})->group('p7-005');

it('rejects an inbound id with a trailing newline (strict end anchor)', function () {
    Log::spy();

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', "abcdefgh\n");

    $response = app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Request-Id'))
        ->not->toBe("abcdefgh\n")
        ->toMatch('/^[0-9a-f-]{36}$/');
})->group('p7-005');

it('adds a correlation id header to real web responses', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Request-Id');
})->group('p7-005');

it('adds a correlation id header to unmatched (404) responses', function () {
    $response = $this->get('/this-route-does-not-exist-p7-005');

    $response->assertNotFound();
    $response->assertHeader('X-Request-Id');
})->group('p7-005');

it('adds a correlation id header to the framework health endpoint', function () {
    $response = $this->get('/up');

    $response->assertOk();
    $response->assertHeader('X-Request-Id');
})->group('p7-005');

it('adds a correlation id header to a CSRF failure response', function () {
    Route::get('/p7-005-csrf-probe', function (): never {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $response = $this->get('/p7-005-csrf-probe');

    $response->assertStatus(419);
    $response->assertHeader('X-Request-Id');
})->group('p7-005');

it('exposes the current request correlation id for propagation', function () {
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', 'trace-propagate-1234');
    app()->instance('request', $request);

    app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    expect(AssignRequestId::currentId())->toBe('trace-propagate-1234');
})->group('p7-005');
