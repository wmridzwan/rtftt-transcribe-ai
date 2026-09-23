<?php

namespace App\Models;

use App\Transcription\LanguageIdentifier;
use Database\Factories\TranscriptRevisionSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persisted segment of a durable revision (P6-002; D6-03/D6-04).
 *
 * `segment_key` is the stable `RevisionSegmentIdentity`; `position` is unique
 * and contiguous per revision. Rows are append-only with their revision.
 *
 * @property int $id
 * @property string $revision_id
 * @property string $segment_key
 * @property int $position
 * @property float $start_seconds
 * @property float $end_seconds
 * @property string $text
 * @property LanguageIdentifier $language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TranscriptRevisionSegment extends Model
{
    /** @use HasFactory<TranscriptRevisionSegmentFactory> */
    use HasFactory;

    protected $table = 'transcript_revision_segments';

    protected $fillable = [
        'revision_id',
        'segment_key',
        'position',
        'start_seconds',
        'end_seconds',
        'text',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'language' => LanguageIdentifier::class,
        ];
    }

    /** @return BelongsTo<TranscriptRevisionModel, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(TranscriptRevisionModel::class, 'revision_id');
    }
}
