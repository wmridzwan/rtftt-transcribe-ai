<?php

namespace App\Actions;

use App\Models\Translation;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * P5-005 abandoned translating-attempt recovery (mirrors ADR-018 / B3-05).
 *
 * A demonstrably stale `translating` translation is moved to a terminal
 * recoverable failure state, after which it becomes eligible for explicit
 * manual retry. Recovery never starts a new inference automatically.
 *
 * Stale authority: guarded compare-and-set (`translating → failed`) so a writer
 * that already completed the translation is never overwritten.
 */
class StaleTranslationAttemptRecovery
{
    /**
     * Derive the stale threshold from the canonical provider execution timeout
     * plus an explicit 60-second safety margin, unless overridden by config.
     */
    public function staleThresholdSeconds(): int
    {
        $configured = config('translation.attempt_stale_seconds');

        if (is_numeric($configured)) {
            return max(1, (int) $configured);
        }

        return max(1, (int) config('translation.timeout_seconds', 300) + 60);
    }

    /**
     * @return int number of stale translations recovered
     */
    public function recover(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subSeconds($this->staleThresholdSeconds());

        $stale = Translation::query()
            ->where('status', TranslationStatus::Translating->value)
            ->whereNotNull('started_at')
            ->whereNotNull('attempt_token')
            ->where('started_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        $recovered = 0;

        foreach ($stale as $translation) {
            // Fenced by attempt identity: a retry between the SELECT and the
            // UPDATE mints a new token, so this CAS cannot fail a newer attempt.
            $won = Translation::query()
                ->whereKey($translation->getKey())
                ->where('status', TranslationStatus::Translating->value)
                ->where('attempt_token', $translation->attempt_token)
                ->update([
                    'status' => TranslationStatus::Failed->value,
                    'failure_code' => TranslationFailure::ProviderTimeout->value,
                    'completed_at' => $now,
                ]);

            if ($won === 1) {
                $recovered++;
            }
        }

        if ($recovered > 0) {
            Log::warning('Recovered stale translation attempts.', ['count' => $recovered]);
        }

        return $recovered;
    }
}
