<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

it('generates and echoes a correlation id when none is supplied', function () {
    Log::spy();

    $response = app(AssignRequestId::class)->handle(
        Request::create('/', 'GET'),
        fn () => new Response('ok'),
    );

    $id = $response->headers->get('X-Request-Id');

    expect($id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
    Log::shouldHaveReceived('withContext')->once()->with(['request_id' => $id]);
})->group('p7-005');

it('honors a well-formed inbound correlation id', function () {
    Log::spy();

    $request = Request::create('/', 'GET');
    $request->headers->set('X-Request-Id', 'trace-abcdef123456');

    $response = app(AssignRequestId::class)->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Request-Id'))->toBe('trace-abcdef123456');
    Log::shouldHaveReceived('withContext')->once()->with(['request_id' => 'trace-abcdef123456']);
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

it('adds a correlation id header to real web responses', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Request-Id');
})->group('p7-005');
