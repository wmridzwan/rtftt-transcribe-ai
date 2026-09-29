<?php

namespace App\Translation;

/**
 * In-process transport seam for the reference external translation adapter.
 *
 * The Wave-1 build performs zero network egress: tests and fixtures supply
 * deterministic in-memory implementations. A future vendor-specific addendum
 * (separate HPO authorization) may add real transports behind this seam; no
 * such transport exists in PP-T4.
 */
interface ReferenceExternalTranslationTransport
{
    public function dispatch(
        ReferenceExternalTranslationRequest $request,
    ): ReferenceExternalTranslationResponse;
}
