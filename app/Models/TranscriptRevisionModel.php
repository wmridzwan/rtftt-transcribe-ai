<?php

namespace App\Models;

use App\Editing\TranscriptRevision;
use Database\Factories\TranscriptRevisionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Persisted editable revision row (P6-002; D6-01/D6-02).
 *
 * Named `TranscriptRevisionModel` to avoid colliding with the immutable domain
 * value object {@see TranscriptRevision}. Rows are append-only:
 * the application never mutates or deletes an existing revision.
 *
 * @property string $id
 * @property int $transcription_id
 * @property int $version
 * @property string|null $parent_revision_id
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TranscriptRevisionModel extends Model
{
    /** @use HasFactory<TranscriptRevisionFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'transcript_revisions';

    protected $fillable = [
        'id',
        'transcription_id',
        'version',
        'parent_revision_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_by' => 'integer',
        ];
    }

    protected static function newFactory(): TranscriptRevisionFactory
    {
        return TranscriptRevisionFactory::new();
    }

    /** @return BelongsTo<Transcription, $this> */
    public function transcription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_revision_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_revision_id')->orderBy('version');
    }

    /** @return HasMany<TranscriptRevisionSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptRevisionSegment::class, 'revision_id')->orderBy('position');
    }
}
