<?php

namespace App\Deployment;

/**
 * Real-host carry-forward evidence store (P7-001, AC8).
 *
 * Binding obligation from `DECISION-P7-008-AC2-DISPOSITION-001`: the P7-008
 * AC2 reboot-cycle verification and the P7-003 AC8 SIGTERM-drain
 * re-confirmation must be performed on the Linux production/staging
 * target before P7-012. This class validates and retains that evidence;
 * it never manufactures it. Until a real-host run is recorded, both
 * items report `pending` — and AC8 as a whole cannot PASS.
 *
 * Payload schema:
 *
 *   [
 *     'host'  => (string) target host identifier,
 *     'items' => [
 *       'ac2-reboot-cycle' => ['result' => 'pass|fail', 'detail' => (string), 'recorded_at' => (string), ...],
 *       'ac8-sigterm-drain' => [...],
 *     ],
 *   ]
 */
final class TargetHostEvidence
{
    public const ITEM_AC2_REBOOT = 'ac2-reboot-cycle';

    public const ITEM_AC8_DRAIN = 'ac8-sigterm-drain';

    /** @var list<string> */
    public const EXPECTED_ITEMS = [self::ITEM_AC2_REBOOT, self::ITEM_AC8_DRAIN];

    public static function path(): string
    {
        return storage_path('app/target-evidence/latest.json');
    }

    /**
     * @return list<string> schema errors; empty when the payload is recordable
     */
    public static function validate(mixed $payload): array
    {
        $errors = [];

        if (! is_array($payload)) {
            return ['Evidence payload must be a JSON object.'];
        }

        if (! isset($payload['host']) || ! is_string($payload['host']) || $payload['host'] === '') {
            $errors[] = 'Evidence payload requires a non-empty string "host".';
        }

        if (! isset($payload['items']) || ! is_array($payload['items'])) {
            $errors[] = 'Evidence payload requires an "items" object.';

            return $errors;
        }

        foreach (self::EXPECTED_ITEMS as $item) {
            $entry = $payload['items'][$item] ?? null;

            if (! is_array($entry)) {
                $errors[] = sprintf('Evidence item "%s" is missing.', $item);

                continue;
            }

            if (($entry['result'] ?? null) !== 'pass' && ($entry['result'] ?? null) !== 'fail') {
                $errors[] = sprintf('Evidence item "%s" requires "result" of "pass" or "fail".', $item);
            }

            if (! isset($entry['detail']) || ! is_string($entry['detail']) || $entry['detail'] === '') {
                $errors[] = sprintf('Evidence item "%s" requires a non-empty "detail".', $item);
            }

            if (! isset($entry['recorded_at']) || ! is_string($entry['recorded_at']) || $entry['recorded_at'] === '') {
                $errors[] = sprintf('Evidence item "%s" requires "recorded_at".', $item);
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException when the payload is not recordable
     */
    public static function record(array $payload): void
    {
        $errors = self::validate($payload);

        if ($errors !== []) {
            throw new \InvalidArgumentException('Unrecordable target evidence: '.implode(' ', $errors));
        }

        $path = self::path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed>|null */
    public static function load(): ?array
    {
        $path = self::path();

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return 'pass'|'fail'|'pending' */
    public static function itemStatus(string $item): string
    {
        $stored = self::load();
        $entry = is_array($stored) && isset($stored['items']) && is_array($stored['items'])
            ? ($stored['items'][$item] ?? null)
            : null;

        if (! is_array($entry)) {
            return 'pending';
        }

        return $entry['result'] === 'pass' ? 'pass' : ($entry['result'] === 'fail' ? 'fail' : 'pending');
    }
}
