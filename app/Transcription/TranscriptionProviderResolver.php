<?php

namespace App\Transcription;

use Illuminate\Support\Facades\Log;

/**
 * Deterministic transcription provider selection (PP-T2).
 *
 * Pure config-driven selection evaluated on every provider-interface
 * resolution (PP-T2 contract §§6/8; kill-switch
 * DECISION-PP-T2-KILL-SWITCH-001; naming
 * DECISION-PP-T2-CONFIG-NAMING-001).
 *
 * Invoked only from TranscriptionServiceProvider::register(). Selection takes
 * no request-scoped arguments and never inspects payload, user, media, or
 * invocation requestId for routing — requestId is correlation only and stays
 * owned by the invocation factories. An optional log-context requestId may be
 * passed purely so resolution records can carry existing correlation; it is
 * never read for selection. External names resolve via the
 * transcription.providers.<name> container binding convention (fixtures
 * and test doubles in PP-T2; real adapters only via later tasks).
 */
final class TranscriptionProviderResolver
{
    public const SELF_HOSTED = 'self_hosted';

    public const SELECTION_CONFIG_KEY = 'transcription.provider_selection';

    public const KILL_SWITCH_CONFIG_KEY = 'processing.external_kill_switch';

    public const EXTERNAL_BINDING_PREFIX = 'transcription.providers.';

    /**
     * Resolve the configured provider.
     *
     * The optional requestId is log correlation only: it is recorded in the
     * resolution log context when provided and is never used for selection.
     */
    public function resolve(?string $requestId = null): TranscriptionProvider
    {
        $killSwitchState = self::normalizeKillSwitch(config(self::KILL_SWITCH_CONFIG_KEY, false));

        if ($killSwitchState !== 'disengaged') {
            if ($killSwitchState === 'invalid') {
                Log::warning('Invalid processing kill-switch value; failing closed to self-hosted transcription.', [
                    'domain' => 'transcription',
                    'configured_value' => config(self::KILL_SWITCH_CONFIG_KEY),
                    'kill_switch_engaged' => true,
                    'request_id' => $requestId,
                ]);
            } else {
                Log::info('Processing kill switch engaged; forcing self-hosted transcription.', [
                    'domain' => 'transcription',
                    'provider_key' => self::SELF_HOSTED,
                    'model_pinned' => (string) config('transcription.model', 'large-v3'),
                    'config_source' => self::KILL_SWITCH_CONFIG_KEY,
                    'kill_switch_engaged' => true,
                    'request_id' => $requestId,
                ]);
            }

            return $this->selfHosted();
        }

        $selection = config(self::SELECTION_CONFIG_KEY, self::SELF_HOSTED);

        if ($selection === self::SELF_HOSTED) {
            $provider = $this->selfHosted();

            Log::info('Transcription provider selected.', [
                'domain' => 'transcription',
                'provider_key' => self::SELF_HOSTED,
                'model_pinned' => (string) config('transcription.model', 'large-v3'),
                'config_source' => self::SELECTION_CONFIG_KEY,
                'kill_switch_engaged' => false,
                'request_id' => $requestId,
            ]);

            return $provider;
        }

        if (! is_string($selection) || ! preg_match('/^external_[A-Za-z0-9_]+$/', $selection)) {
            Log::warning('Unknown transcription provider selection; failing closed.', [
                'domain' => 'transcription',
                'configured_value' => self::describeSelection($selection),
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                'Unknown transcription provider selection ['.self::describeSelection($selection).'].'
            );
        }

        $binding = self::EXTERNAL_BINDING_PREFIX.$selection;

        if (! app()->bound($binding)) {
            Log::warning('Transcription provider binding missing; failing closed.', [
                'domain' => 'transcription',
                'configured_value' => $selection,
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                "Transcription provider [{$selection}] has no container binding [{$binding}]."
            );
        }

        $provider = app($binding);

        if (! $provider instanceof TranscriptionProvider) {
            Log::warning('Transcription provider binding invalid; failing closed.', [
                'domain' => 'transcription',
                'configured_value' => $selection,
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                "Transcription provider binding [{$binding}] does not implement TranscriptionProvider."
            );
        }

        Log::info('Transcription provider selected.', [
            'domain' => 'transcription',
            'provider_key' => $selection,
            'model_pinned' => 'unknown',
            'config_source' => self::SELECTION_CONFIG_KEY,
            'kill_switch_engaged' => false,
            'request_id' => $requestId,
        ]);

        return $provider;
    }

    /**
     * Validate the selection value shape (boot-time guard).
     *
     * Checks the configured value format only — never constructs a
     * provider and never requires external bindings to exist yet.
     */
    public static function validateSelection(): void
    {
        $selection = config(self::SELECTION_CONFIG_KEY, self::SELF_HOSTED);

        if ($selection !== self::SELF_HOSTED
            && (! is_string($selection) || ! preg_match('/^external_[A-Za-z0-9_]+$/', $selection))
        ) {
            Log::warning('Invalid transcription provider selection at boot validation.', [
                'domain' => 'transcription',
                'configured_value' => self::describeSelection($selection),
            ]);

            throw new ProviderResolutionException(
                'Invalid transcription provider selection ['.self::describeSelection($selection).'].'
            );
        }
    }

    private function selfHosted(): TranscriptionProvider
    {
        return new SelfHostedTranscriptionProvider(
            workerBaseUrl: (string) config('transcription.worker_url', 'http://localhost:8000'),
            bearerToken: (string) config('transcription.worker_token', ''),
        );
    }

    /**
     * Normalize the kill-switch raw value: engaged, disengaged, or invalid.
     */
    private static function normalizeKillSwitch(mixed $raw): string
    {
        if (is_bool($raw)) {
            return $raw ? 'engaged' : 'disengaged';
        }

        if (is_int($raw)) {
            return match ($raw) {
                1 => 'engaged',
                0 => 'disengaged',
                default => 'invalid',
            };
        }

        if (is_string($raw)) {
            return match (strtolower($raw)) {
                '1', 'true' => 'engaged',
                '0', 'false' => 'disengaged',
                default => 'invalid',
            };
        }

        if ($raw === null) {
            return 'disengaged';
        }

        return 'invalid';
    }

    private static function describeSelection(mixed $selection): string
    {
        if (is_string($selection)) {
            return $selection === '' ? '(empty)' : $selection;
        }

        return '('.get_debug_type($selection).')';
    }
}
