<?php

namespace App\Models;

use App\Transcription\LanguageIdentifier;
use Database\Factories\TranslationSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $translation_id
 * @property int $segment_index
 * @property float $start_seconds
 * @property float $end_seconds
 * @property string $text
 * @property LanguageIdentifier $source_language
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TranslationSegment extends Model
{
    /** @use HasFactory<TranslationSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'translation_id',
        'segment_index',
        'start_seconds',
        'end_seconds',
        'text',
        'source_language',
    ];

    protected function casts(): array
    {
        return [
            'segment_index' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'source_language' => LanguageIdentifier::class,
        ];
    }

    /** @return BelongsTo<Translation, $this> */
    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }
}
