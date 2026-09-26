<?php

namespace App\Capacity;

/**
 * Repeat summarization for P7-009 runs.
 *
 * Preserves every individual repeat value; the summary reports count,
 * min, max, mean, median, and population standard deviation. Outliers
 * are never discarded to improve numbers: when a repeat is excluded
 * from an interpretation for a known invalid test condition, the raw
 * value stays retained and the exclusion is explained in
 * `exclusion_notes`.
 */
final class CapacityStats
{
    /**
     * @param  list<float>  $values
     * @param  list<string>  $exclusionNotes
     * @return array{count: int, min: ?float, max: ?float, mean: ?float, median: ?float, stddev: ?float, values: list<float>, exclusion_notes: list<string>}
     */
    public static function summarize(array $values, array $exclusionNotes = []): array
    {
        $count = count($values);

        if ($count === 0) {
            return [
                'count' => 0,
                'min' => null,
                'max' => null,
                'mean' => null,
                'median' => null,
                'stddev' => null,
                'values' => [],
                'exclusion_notes' => $exclusionNotes,
            ];
        }

        sort($values);
        $mean = array_sum($values) / $count;
        $mid = intdiv($count, 2);
        $median = $count % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        $variance /= $count;

        return [
            'count' => $count,
            'min' => $values[0],
            'max' => $values[$count - 1],
            'mean' => $mean,
            'median' => $median,
            'stddev' => sqrt($variance),
            'values' => $values,
            'exclusion_notes' => $exclusionNotes,
        ];
    }
}
