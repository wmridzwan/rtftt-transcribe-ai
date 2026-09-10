<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transcription_id
 * @property int $segment_index
 * @property int $start_seconds
 * @property int $end_seconds
 * @property string $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TranscriptionSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transcription_id',
        'segment_index',
        'start_seconds',
        'end_seconds',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'segment_index' => 'integer',
            'start_seconds' => 'integer',
            'end_seconds' => 'integer',
        ];
    }

    public function transcription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class);
    }

    public function getFormattedStartAttribute(): string
    {
        $hours = intdiv($this->start_seconds, 3600);
        $minutes = intdiv($this->start_seconds % 3600, 60);
        $seconds = $this->start_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedEndAttribute(): string
    {
        $hours = intdiv($this->end_seconds, 3600);
        $minutes = intdiv($this->end_seconds % 3600, 60);
        $seconds = $this->end_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
