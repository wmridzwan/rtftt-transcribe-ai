<?php

namespace App\Models;

use App\TranscriptExperience\SegmentTimestamp;
use App\Transcription\LanguageIdentifier;
use Database\Factories\TranscriptionSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transcription_id
 * @property int $segment_index
 * @property float $start_seconds
 * @property float $end_seconds
 * @property string $text
 * @property LanguageIdentifier $language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TranscriptionSegment extends Model
{
    /** @use HasFactory<TranscriptionSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'transcription_id',
        'segment_index',
        'start_seconds',
        'end_seconds',
        'text',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'segment_index' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'language' => LanguageIdentifier::class,
        ];
    }

    /** @return BelongsTo<Transcription, $this> */
    public function transcription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class);
    }

    public function getFormattedStartAttribute(): string
    {
        $totalSeconds = (int) floor($this->start_seconds);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Exact persisted numeric seconds for media seeking (P4-001 canonical
     * primitive); never re-rounded and never parsed from the display string.
     */
    public function getSeekSecondsAttribute(): string
    {
        return SegmentTimestamp::fromSeconds($this->start_seconds)->seek();
    }

    public function getFormattedEndAttribute(): string
    {
        $totalSeconds = (int) floor($this->end_seconds);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
