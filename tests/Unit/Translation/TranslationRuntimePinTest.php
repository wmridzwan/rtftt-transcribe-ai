<?php

/*
 * Guard test for the canonical Phase 5 translation worker runtime (ADR-024).
 *
 * The declared pins in `worker/requirements.txt` are the single canonical
 * translation runtime. The P5-008 real gate must run against exactly this set,
 * so a drift here must fail the suite rather than silently diverge from the
 * gated runtime. Mirrors the worker-side guard in
 * `worker/tests/test_requirements.py`.
 *
 * This is a pure unit test (no application boot): it reads the repository file
 * directly.
 */

function p5RuntimePinContents(): string
{
    $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'worker'.DIRECTORY_SEPARATOR.'requirements.txt';

    return (string) file_get_contents($path);
}

/**
 * Active (non-comment) requirement lines only, so the historical supersede note
 * in the header comment cannot mask a real pin regression.
 */
function p5RuntimeActiveLines(): string
{
    $lines = preg_split('/\R/', p5RuntimePinContents()) ?: [];

    return implode("\n", array_filter(
        $lines,
        static fn (string $line): bool => ! str_starts_with(trim($line), '#'),
    ));
}

it('pins the canonical translation runtime exactly', function () {
    $contents = p5RuntimeActiveLines();

    $expected = [
        'transformers' => '5.17.0',
        'torch' => '2.14.0',
        'sentencepiece' => '0.2.2',
    ];

    foreach ($expected as $package => $version) {
        expect($contents)->toContain("{$package}=={$version}")
            ->and($contents)->not->toContain("{$package}>=");
    }
});

it('does not declare the superseded 4.x translation runtime as canonical', function () {
    $contents = p5RuntimeActiveLines();

    expect($contents)->not->toContain('transformers==4.57.6')
        ->and($contents)->not->toContain('torch==2.9.1')
        ->and($contents)->not->toContain('sentencepiece==0.2.1');
});
