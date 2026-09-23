<?php

namespace App\Editing\Persistence;

use App\Editing\MachineSegmentSnapshot;
use App\Editing\RevisionFactory;
use App\Editing\TranscriptRevision;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;

/**
 * Maps completed machine transcription rows into a pure initial revision
 * (P6-002; D6-01).
 *
 * The machine source is read-only here: only `MachineSegmentSnapshot` value
 * objects cross into the domain layer, and no machine row is ever written.
 */
final class MachineSourceMaterializer
{
    public function __construct(private readonly RevisionFactory $factory) {}

    public function materialize(Transcription $transcription, int $createdBy): TranscriptRevision
    {
        /** @var list<MachineSegmentSnapshot> $snapshots */
        $snapshots = array_values(
            $transcription->segments()
                ->get()
                ->map(fn (TranscriptionSegment $segment): MachineSegmentSnapshot => new MachineSegmentSnapshot(
                    segmentIndex: $segment->segment_index,
                    startSeconds: $segment->start_seconds,
                    endSeconds: $segment->end_seconds,
                    text: $segment->text,
                    language: $segment->language,
                ))
                ->all()
        );

        return $this->factory->materializeInitial(
            $transcription->getKey(),
            $createdBy,
            $snapshots,
        );
    }
}
