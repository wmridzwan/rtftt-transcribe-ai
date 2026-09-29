<?php

namespace Tests\Support;

use App\Transcription\ReferenceExternalChunkRequest;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTransport;
use RuntimeException;

/**
 * Deterministic in-process fake for the PP-T3 reference external boundary.
 *
 * Scripts per-chunk responses by chunk index; records every outbound request
 * for exact-field assertions. Never performs network I/O.
 */
final class FakeReferenceExternalTransport implements ReferenceExternalTransport
{
    /** @var list<ReferenceExternalChunkRequest> */
    public array $requests = [];

    /** @var array<int, ReferenceExternalChunkResponse> */
    public array $script = [];

    /** @var array<int, bool> chunk indexes that must behave as dropped in transit */
    public array $dropped = [];

    public function __construct(
        private readonly ?ReferenceExternalChunkResponse $default = null,
    ) {}

    public function dispatchChunk(
        ReferenceExternalChunkRequest $request,
    ): ReferenceExternalChunkResponse {
        $this->requests[] = $request;

        if (($this->dropped[$request->chunkIndex] ?? false) === true) {
            throw new RuntimeException("Chunk [{$request->chunkId}] dropped in transit (fixture).");
        }

        return $this->script[$request->chunkIndex]
            ?? $this->default
            ?? ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);
    }

    public function dispatchCount(): int
    {
        return count($this->requests);
    }
}
