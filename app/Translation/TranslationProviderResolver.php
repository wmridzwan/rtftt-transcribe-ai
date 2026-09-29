<?php

namespace App\Translation;

use Illuminate\Support\Facades\Log;

/**
 * Deterministic translation provider selection (PP-T2).
 *
 * Pure config-driven selection evaluated on every provider-interface
 * resolution (PP-T2 contract §§6/8; kill-switch
 * DECISION-PP-T2-KILL-SWITCH-001; naming
 * DECISION-PP-T2-CONFIG-NAMING-001). Wave 1 locks translation to
 * self-hosted: any other selection value is a validation failure, except
 * the single PP-T4 fixture value below (scoped
 * DECISION-PP-T4-CONTRACT-RECONCILIATION-001, H-3).
 *
 * Invoked only from AppServiceProvider::register(). Selection takes no
 * request-scoped arguments and never inspects payload, user, media, or
 * invocation requestId for routing — requestId is correlation only and stays
 * owned by the invocation factories. An optional log-context requestId may be
 * passed purely so resolution records can carry existing correlation; it is
 * never read for selection.
 */
final class TranslationProviderResolver
{
    public const SELF_HOSTED = 'self_hosted';

    /**
     * Scoped PP-T4 fixture selection (H-3). The ONLY non-self_hosted value
     * the resolver honors, resolving solely to the fixture reference adapter
     * bound under the translation.providers.* convention. Any other
     * external_* value remains rejected: general translation unlocking stays
     * forbidden without a fresh HPO decision.
     */
    public const FIXTURE_REFERENCE = 'external_reference';

    public const SELECTION_CONFIG_KEY = 'translation.provider_selection';

    public const KILL_SWITCH_CONFIG_KEY = 'processing.external_kill_switch';

    public const EXTERNAL_BINDING_PREFIX = 'translation.providers.';

    /**
     * Resolve the configured provider.
     *
     * The optional requestId is log correlation only: it is recorded in the
     * resolution log context when provided and is never used for selection.
     */
    public function resolve(?string $requestId = null): TranslationProvider
    {
        $killSwitchState = self::normalizeKillSwitch(config(self::KILL_SWITCH_CONFIG_KEY, false));

        if ($killSwitchState !== 'disengaged') {
            if ($killSwitchState === 'invalid') {
                Log::warning('Invalid processing kill-switch value; failing closed to self-hosted translation.', [
                    'domain' => 'translation',
                    'configured_value' => config(self::KILL_SWITCH_CONFIG_KEY),
                    'kill_switch_engaged' => true,
                    'request_id' => $requestId,
                ]);
            } else {
                Log::info('Processing kill switch engaged; forcing self-hosted translation.', [
                    'domain' => 'translation',
                    'provider_key' => self::SELF_HOSTED,
                    'model_pinned' => (string) config('translation.model', 'self-hosted-default'),
                    'config_source' => self::KILL_SWITCH_CONFIG_KEY,
                    'kill_switch_engaged' => true,
                    'request_id' => $requestId,
                ]);
            }

            return $this->selfHosted();
        }

        $selection = config(self::SELECTION_CONFIG_KEY, self::SELF_HOSTED);

        if ($selection === self::FIXTURE_REFERENCE) {
            return $this->fixtureReference($requestId);
        }

        if ($selection !== self::SELF_HOSTED) {
            Log::warning('Unsupported translation provider selection; failing closed.', [
                'domain' => 'translation',
                'configured_value' => self::describeSelection($selection),
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                'Unsupported translation provider selection ['.self::describeSelection($selection).']; Wave 1 supports self_hosted only.'
            );
        }

        $provider = $this->selfHosted();

        Log::info('Translation provider selected.', [
            'domain' => 'translation',
            'provider_key' => self::SELF_HOSTED,
            'model_pinned' => (string) config('translation.model', 'self-hosted-default'),
            'config_source' => self::SELECTION_CONFIG_KEY,
            'kill_switch_engaged' => false,
            'request_id' => $requestId,
        ]);

        return $provider;
    }

    /**
     * Validate the selection value shape (boot-time guard).
     *
     * Wave 1 accepts self_hosted plus the single PP-T4 fixture value
     * external_reference (H-3). Never constructs a provider.
     */
    public static function validateSelection(): void
    {
        $selection = config(self::SELECTION_CONFIG_KEY, self::SELF_HOSTED);

        if ($selection !== self::SELF_HOSTED && $selection !== self::FIXTURE_REFERENCE) {
            Log::warning('Invalid translation provider selection at boot validation.', [
                'domain' => 'translation',
                'configured_value' => self::describeSelection($selection),
            ]);

            throw new ProviderResolutionException(
                'Invalid translation provider selection ['.self::describeSelection($selection).']; Wave 1 supports self_hosted only.'
            );
        }
    }

    private function selfHosted(): TranslationProvider
    {
        return new SelfHostedTranslationProvider(
            workerBaseUrl: (string) config('translation.worker_url', 'http://localhost:8000'),
            bearerToken: (string) config('translation.worker_token', ''),
            providerName: (string) config('translation.provider', 'self-hosted'),
            model: (string) config('translation.model', 'self-hosted-default'),
            contractVersion: (string) config('translation.contract_version', '1.0'),
        );
    }

    /**
     * Resolve the scoped PP-T4 fixture binding (H-3).
     *
     * Fail-closed preserved: a missing binding or a binding that does not
     * implement TranslationProvider throws before any dispatch (zero
     * egress). Reached only with the kill switch disengaged.
     */
    private function fixtureReference(?string $requestId): TranslationProvider
    {
        $binding = self::EXTERNAL_BINDING_PREFIX.self::FIXTURE_REFERENCE;

        if (! app()->bound($binding)) {
            Log::warning('Translation fixture provider binding missing; failing closed.', [
                'domain' => 'translation',
                'configured_value' => self::FIXTURE_REFERENCE,
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                'Translation provider ['.self::FIXTURE_REFERENCE."] has no container binding [{$binding}]."
            );
        }

        $provider = app($binding);

        if (! $provider instanceof TranslationProvider) {
            Log::warning('Translation fixture provider binding invalid; failing closed.', [
                'domain' => 'translation',
                'configured_value' => self::FIXTURE_REFERENCE,
                'request_id' => $requestId,
            ]);

            throw new ProviderResolutionException(
                "Translation provider binding [{$binding}] does not implement TranslationProvider."
            );
        }

        Log::info('Translation provider selected.', [
            'domain' => 'translation',
            'provider_key' => self::FIXTURE_REFERENCE,
            'model_pinned' => 'unknown',
            'config_source' => self::SELECTION_CONFIG_KEY,
            'kill_switch_engaged' => false,
            'request_id' => $requestId,
        ]);

        return $provider;
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
