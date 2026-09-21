<?php

namespace App\Http\Requests;

use App\Models\Transcription;
use App\Translation\TranslationTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Start a translation for a target language (P5-006).
 *
 * Authorization runs before validation, so a user who may not act on the
 * transcription learns nothing about which targets are valid.
 */
class StartTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transcription = $this->route('transcription');

        return $transcription instanceof Transcription
            && $this->user() !== null
            && $this->user()->can('update', $transcription);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_language' => ['required', 'string', Rule::enum(TranslationTarget::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_language.required' => 'Choose a target language.',
            'target_language.enum' => 'That target language is not supported.',
        ];
    }

    public function target(): TranslationTarget
    {
        return TranslationTarget::from((string) $this->validated('target_language'));
    }
}
