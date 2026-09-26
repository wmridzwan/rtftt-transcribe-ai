<?php

namespace App\Deployment;

use App\Security\SecurityAuditLog;
use LogicException;

/**
 * Production environment/config safety guard (P7-008).
 *
 * Validates the production env matrix and fails fast at deploy/boot time
 * when a setting is missing or unsafe. Only enforced when the application
 * runs in the production environment; the pure `violations()` logic is
 * covered directly by tests.
 *
 * This guard composes (never duplicates) the queue guards: queue-driver
 * and timeout consistency remain owned by `TranscriptionQueueConfig` /
 * `TranslationQueueConfig` (P7-003).
 */
final class ProductionConfigGuard
{
    /**
     * @return list<string> human-readable violations; empty when safe
     */
    public static function violations(): array
    {
        $violations = [];

        if (filter_var(config('app.debug'), FILTER_VALIDATE_BOOLEAN)) {
            $violations[] = 'APP_DEBUG must be false in production.';
        }

        $queueDefault = config('queue.default');

        if ($queueDefault !== 'redis') {
            $violations[] = sprintf(
                'QUEUE_CONNECTION must be "redis" in production (D7-02); got "%s".',
                is_scalar($queueDefault) ? (string) $queueDefault : get_debug_type($queueDefault)
            );
        }

        $appKey = (string) config('app.key', '');

        if ($appKey === '' || str_starts_with($appKey, 'base64:') === false && strlen($appKey) < 32) {
            $violations[] = 'APP_KEY must be set to a generated application key in production.';
        }

        foreach ([
            'transcription.worker_url' => 'RTFTT_TRANSCRIPTION_WORKER_URL',
            'translation.worker_url' => 'RTFTT_TRANSLATION_WORKER_URL',
        ] as $key => $env) {
            $url = config($key);

            if (! is_string($url) || $url === '' || str_starts_with($url, 'http://localhost')) {
                $violations[] = sprintf(
                    '%s must point at the production worker base URL in production (env %s).',
                    $key,
                    $env
                );
            }
        }

        foreach ([
            'transcription.worker_token' => 'RTFTT_TRANSCRIPTION_WORKER_TOKEN',
            'translation.worker_token' => 'RTFTT_TRANSLATION_WORKER_TOKEN',
        ] as $key => $env) {
            $token = config($key);

            if (! is_string($token) || $token === '') {
                $violations[] = sprintf(
                    '%s must be provisioned in production (env %s); worker calls are authenticated.',
                    $key,
                    $env
                );
            }
        }

        return $violations;
    }

    /**
     * @throws LogicException when production settings are missing or unsafe
     */
    public static function assertValid(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $violations = self::violations();

        if ($violations !== []) {
            // P7-006: audit the refusal with key names only (values never
            // enter the log); the messages themselves name keys, not values.
            SecurityAuditLog::guardRefusal('ProductionConfigGuard', count($violations).' violation(s)');

            throw new LogicException(
                'Unsafe production configuration: '.implode(' ', $violations)
            );
        }
    }
}
