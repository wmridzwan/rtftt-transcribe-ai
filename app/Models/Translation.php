<?php

namespace App\Models;

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationFailure;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transcription_id
 * @property TranslationTarget $target_language
 * @property TranslationStatus $status
 * @property string|null $attempt_token
 * @property Carbon|null $dispatched_at
 * @property LanguageIdentifier|null $source_language
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $full_text
 * @property TranslationFailure|null $failure_code
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'transcription_id',
        'target_language',
        'status',
        'attempt_token',
        'dispatched_at',
        'source_language',
        'provider',
        'model',
        'full_text',
        'failure_code',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_language' => TranslationTarget::class,
            'status' => TranslationStatus::class,
            'source_language' => LanguageIdentifier::class,
            'failure_code' => TranslationFailure::class,
            'dispatched_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Transcription, $this> */
    public function transcription(): BelongsTo
    {
        return $this->belongsTo(Transcription::class);
    }

    /** @return HasMany<TranslationSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(TranslationSegment::class)->orderBy('segment_index');
    }

    public function isCompleted(): bool
    {
        return $this->status === TranslationStatus::Completed;
    }

    /**
     * A queued attempt whose message never reached the queue (dispatch failed).
     */
    public function isAwaitingDispatch(): bool
    {
        return $this->status === TranslationStatus::Queued
            && $this->attempt_token !== null
            && $this->dispatched_at === null;
    }
}
