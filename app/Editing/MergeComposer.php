<?php

namespace App\Editing;

use App\Transcription\LanguageIdentifier;
use InvalidArgumentException;

/**
 * Composes a structural merge over a base revision's segments (P6-005).
 *
 * A merge replaces an ordered run of two or more **adjacent** revision segments
 * with one segment at the earliest replaced position. The merged segment
 * receives a **new, opaque** {@see RevisionSegmentIdentity}; the contributing
 * identities are removed only from the new revision. The immutable machine
 * source is never touched.
 *
 * Text is joined in contributor (position) order with one canonical plain-space
 * separator and is never trimmed or rewritten
 * (DECISION-P6-005-MERGE-JOIN-001). Timing is the earliest-position contributor
 * start to the latest-position contributor end. Same-language contributors
 * retain their language; differing contributors yield `und` plus explicit
 * ordered {@see LanguageProvenance} (DECISION-P6-005-LANGUAGE-PROVENANCE-001).
 */
final class MergeComposer
{
    /**
     * @param  list<RevisionSegmentData>  $base  the base revision segments
     * @param  list<string>  $segmentKeys  the contributing identities (>= 2)
     * @return list<RevisionSegmentData> ordered by position ascending
     *
     * @throws InvalidArgumentException when fewer than two contributors are
     *                                  supplied, a contributor is unknown or
     *                                  duplicated, or the contributors are not
     *                                  adjacent in `position`
     */
    public function compose(array $base, array $segmentKeys): array
    {
        if (count($segmentKeys) < 2) {
            throw new InvalidArgumentException('A merge requires at least two adjacent revision segments.');
        }

        $byKey = [];

        foreach ($base as $segment) {
            $byKey[$segment->identity->key()] = $segment;
        }

        /** @var list<RevisionSegmentData> $contributors */
        $contributors = [];
        $seen = [];

        foreach ($segmentKeys as $key) {
            if ($key === '') {
                throw new InvalidArgumentException('Merge contributor identities must be non-empty strings.');
            }

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Merge contributor identity ['.$key.'] was supplied more than once.');
            }

            $seen[$key] = true;

            if (! isset($byKey[$key])) {
                throw new InvalidArgumentException('Unknown revision segment identity ['.$key.'] for merge.');
            }

            $contributors[] = $byKey[$key];
        }

        usort($contributors, static fn (RevisionSegmentData $a, RevisionSegmentData $b): int => $a->position <=> $b->position);

        $first = $contributors[0];
        $last = $contributors[count($contributors) - 1];

        for ($index = 1; $index < count($contributors); $index++) {
            if ($contributors[$index]->position !== $contributors[$index - 1]->position + 1) {
                throw new InvalidArgumentException('Only adjacent revision segments may be merged; the selected segments are not contiguous.');
            }
        }

        $start = $first->position;
        $end = $last->position;
        $mergedCount = count($contributors);

        $texts = array_map(static fn (RevisionSegmentData $segment): string => $segment->text, $contributors);
        $joinedText = implode(' ', $texts);

        $languages = array_map(static fn (RevisionSegmentData $segment): LanguageIdentifier => $segment->language, $contributors);
        [$language, $provenance] = $this->resolveLanguage($languages);

        $merged = new RevisionSegmentData(
            identity: RevisionSegmentIdentity::forStructuralEdit(),
            position: $start,
            startSeconds: $first->startSeconds,
            endSeconds: $last->endSeconds,
            text: $joinedText,
            language: $language,
            languageProvenance: $provenance,
        );

        $ordered = $base;
        usort($ordered, static fn (RevisionSegmentData $a, RevisionSegmentData $b): int => $a->position <=> $b->position);

        $result = [];

        foreach ($ordered as $segment) {
            if ($segment->position < $start) {
                $result[] = $segment;

                continue;
            }

            if ($segment->position > $end) {
                $result[] = new RevisionSegmentData(
                    identity: $segment->identity,
                    position: $segment->position - ($mergedCount - 1),
                    startSeconds: $segment->startSeconds,
                    endSeconds: $segment->endSeconds,
                    text: $segment->text,
                    language: $segment->language,
                    languageProvenance: $segment->languageProvenance,
                );
            }
        }

        array_splice($result, $start, 0, [$merged]);

        return $result;
    }

    /**
     * @param  list<LanguageIdentifier>  $languages
     * @return array{0: LanguageIdentifier, 1: LanguageProvenance|null}
     */
    private function resolveLanguage(array $languages): array
    {
        $values = array_map(static fn (LanguageIdentifier $language): string => $language->value, $languages);

        if (count(array_unique($values)) === 1) {
            return [$languages[0], null];
        }

        return [LanguageIdentifier::Undetermined, LanguageProvenance::fromIdentifiers($languages)];
    }
}
