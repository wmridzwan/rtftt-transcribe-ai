<?php

namespace Tests\Support;

use App\Translation\ReferenceExternalTranslationRequest;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\ReferenceExternalTranslationTransport;
use RuntimeException;

/**
 * Deterministic in-process fake for the PP-T4 reference external boundary.
 *
 * Scripts one response (or a default); records every outbound request for
 * exact-field assertions. Never performs network I/O.
 */
final class FakeReferenceExternalTranslationTransport implements ReferenceExternalTranslationTransport
{
    /** @var list<ReferenceExternalTranslationRequest> */
    public array $requests = [];

    public bool $dropNext = false;

    public function __construct(
        private readonly ?ReferenceExternalTranslationResponse $scripted = null,
    ) {}

    public function dispatch(
        ReferenceExternalTranslationRequest $request,
    ): ReferenceExternalTranslationResponse {
        $this->requests[] = $request;

        if ($this->dropNext) {
            $this->dropNext = false;

            throw new RuntimeException("Translation request [{$request->requestId}] dropped in transit (fixture).");
        }

        return $this->scripted
            ?? ReferenceExternalTranslationResponse::success(
                targetLanguage: $request->targetLanguage,
                text: '',
                segments: [],
            );
    }

    public function dispatchCount(): int
    {
        return count($this->requests);
    }
}
