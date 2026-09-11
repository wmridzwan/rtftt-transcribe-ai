# TASK-P1-STATIC-001 — full static-analysis triage

Author: Codex. Implementation evidence, not independent review.

Command: PHP 8.4 vendor/bin/phpstan analyse --memory-limit=512M --no-progress --error-format=json -v. Result: 59 errors. No suppressions or configuration changes.

## Classification and repair contracts

- HIGH: DOCX export uses a title-derived shared temporary path and does not handle file_get_contents failure. Concurrent same-title requests can overwrite/read/delete another export. TASK-P1-STATIC-002 will isolate temporary files, guarantee cleanup and preserve download/ownership contracts, with regression coverage.
- MEDIUM: UserFactory::withTwoFactor has no return statement despite static return type. Calling it throws TypeError. Repair the factory helper only; do not enable 2FA, change schema or public product behavior. TASK-P1-STATIC-004.
- MEDIUM: DatabaseSeeder job timestamps are assigned conditionally inside attribute arrays, then read unconditionally; variables can be undefined or leak between iterations, and DateTime is incorrectly checked against Carbon when rendering logs. TASK-P1-STATIC-004 will make per-record times explicit and ordered, maintain demo statuses/ownership and test queued/completed/running behavior. Unreachable match arms are caused by the finite fixture set; remove unreachable cases while preserving current fixture output.
- LOW/MEDIUM: Eloquent relationship/factory generics and array value types are missing; User::$role PHPDoc incorrectly says string although cast is UserRole. Segment/media property errors are downstream of missing relationship generics. TASK-P1-STATIC-003 will correct declarations at their source without casts, inline assertions, ignores or configuration relaxation. Existing domain/authorization/page tests plus focused contract coverage protect runtime behavior.
- LOW: Faker words(2, true) has an array|string declared return type; use a typed words-array plus implode without changing filename semantics. Included in TASK-P1-STATIC-004.

No Human Product Owner decision is required for these bounded repairs of the already approved Phase 1 contract. Current user authorization permits continuing runnable follow-ups to a genuine human/acceptance gate. Phase 2 remains unauthorized.

## Complete inventory

| File | Line | Identifier | Message |
|---|---:|---|---|
| app/Http/Controllers/MediaActionController.php | 48 | missingType.return | Method App\Http\Controllers\MediaActionController::download() has no return type specified. |
| app/Http/Controllers/TranscriptionExportController.php | 52 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$start_seconds. |
| app/Http/Controllers/TranscriptionExportController.php | 53 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$end_seconds. |
| app/Http/Controllers/TranscriptionExportController.php | 54 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$text. |
| app/Http/Controllers/TranscriptionExportController.php | 77 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$start_seconds. |
| app/Http/Controllers/TranscriptionExportController.php | 78 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$end_seconds. |
| app/Http/Controllers/TranscriptionExportController.php | 79 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$text. |
| app/Http/Controllers/TranscriptionExportController.php | 109 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$text. |
| app/Http/Controllers/TranscriptionExportController.php | 122 | argument.type | Parameter #1 $content of function response expects array\|Illuminate\Contracts\View\View\|string\|null, string\|false given. |
| app/Livewire/Folders/Index.php | 31 | missingType.return | Method App\Livewire\Folders\Index::render() has no return type specified. |
| app/Livewire/Media/Show.php | 17 | missingType.iterableValue | Property App\Livewire\Media\Show::$folders type has no value type specified in iterable type array. |
| app/Livewire/Media/Show.php | 31 | missingType.return | Method App\Livewire\Media\Show::render() has no return type specified. |
| app/Models/Folder.php | 20 | missingType.generics | Class App\Models\Folder uses generic trait Illuminate\Database\Eloquent\Factories\HasFactory but does not specify its types: TFactory |
| app/Models/Folder.php | 27 | missingType.generics | Method App\Models\Folder::user() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Folder.php | 32 | missingType.generics | Method App\Models\Folder::mediaFiles() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/MediaFile.php | 36 | missingType.generics | Class App\Models\MediaFile uses generic trait Illuminate\Database\Eloquent\Factories\HasFactory but does not specify its types: TFactory |
| app/Models/MediaFile.php | 80 | missingType.generics | Method App\Models\MediaFile::user() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/MediaFile.php | 85 | missingType.generics | Method App\Models\MediaFile::folder() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/MediaFile.php | 90 | missingType.generics | Method App\Models\MediaFile::transcriptions() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/ProcessingJob.php | 29 | missingType.iterableValue | Class App\Models\ProcessingJob has PHPDoc tag @property for property $logs with no value type specified in iterable type array. |
| app/Models/ProcessingJob.php | 31 | missingType.generics | Class App\Models\ProcessingJob uses generic trait Illuminate\Database\Eloquent\Factories\HasFactory but does not specify its types: TFactory |
| app/Models/ProcessingJob.php | 69 | missingType.generics | Method App\Models\ProcessingJob::transcription() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Transcription.php | 31 | missingType.generics | Class App\Models\Transcription uses generic trait Illuminate\Database\Eloquent\Factories\HasFactory but does not specify its types: TFactory |
| app/Models/Transcription.php | 58 | missingType.generics | Method App\Models\Transcription::user() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Transcription.php | 63 | missingType.generics | Method App\Models\Transcription::mediaFile() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Transcription.php | 68 | missingType.generics | Method App\Models\Transcription::segments() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Transcription.php | 73 | missingType.generics | Method App\Models\Transcription::processingJobs() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/Transcription.php | 80 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$formatted_duration. |
| app/Models/Transcription.php | 102 | property.notFound | Access to an undefined property Illuminate\Database\Eloquent\Model::$duration_seconds. |
| app/Models/TranscriptionSegment.php | 22 | missingType.generics | Class App\Models\TranscriptionSegment uses generic trait Illuminate\Database\Eloquent\Factories\HasFactory but does not specify its types: TFactory |
| app/Models/TranscriptionSegment.php | 41 | missingType.generics | Method App\Models\TranscriptionSegment::transcription() return type with generic class Illuminate\Database\Eloquent\Relations\BelongsTo does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/User.php | 57 | identical.alwaysFalse | Strict comparison using === between string and App\Enums\UserRole::Admin will always evaluate to false. |
| app/Models/User.php | 60 | missingType.generics | Method App\Models\User::folders() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/User.php | 65 | missingType.generics | Method App\Models\User::mediaFiles() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| app/Models/User.php | 70 | missingType.generics | Method App\Models\User::transcriptions() return type with generic class Illuminate\Database\Eloquent\Relations\HasMany does not specify its types: TRelatedModel, TDeclaringModel |
| database/factories/MediaFileFactory.php | 26 | binaryOp.invalid | Binary operation "." between array\|string and '.' results in an error. |
| database/factories/UserFactory.php | 44 | return.missing | Method Database\Factories\UserFactory::withTwoFactor() should return static(Database\Factories\UserFactory) but return statement is missing. |
| database/seeders/DatabaseSeeder.php | 173 | match.alwaysFalse | Match arm comparison between 'mp4' and 'm4a' is always false. |
| database/seeders/DatabaseSeeder.php | 174 | match.alwaysFalse | Match arm comparison between 'mp4' and 'wav' is always false. |
| database/seeders/DatabaseSeeder.php | 180 | match.alwaysFalse | Match arm comparison between 'mp4' and 'm4a' is always false. |
| database/seeders/DatabaseSeeder.php | 181 | match.alwaysFalse | Match arm comparison between 'mp4' and 'wav' is always false. |
| database/seeders/DatabaseSeeder.php | 182 | match.alwaysTrue | Match arm comparison between 'mp4' and 'mp4' is always true. |
| database/seeders/DatabaseSeeder.php | 190 | match.alwaysTrue | Match arm comparison between 4 and 4 is always true. |
| database/seeders/DatabaseSeeder.php | 207 | function.alreadyNarrowedType | Call to function in_array() with App\Enums\TranscriptionStatus::Completed\|App\Enums\TranscriptionStatus::Failed\|App\Enums\TranscriptionStatus::Transcribing and array{App\Enums\TranscriptionStatus::Transcribing, App\Enums\TranscriptionStatus::Completed, App\Enums\TranscriptionStatus::Failed} will always evaluate to true. |
| database/seeders/DatabaseSeeder.php | 231 | notIdentical.alwaysTrue | Strict comparison using !== between App\Enums\TranscriptionStatus::Completed\|App\Enums\TranscriptionStatus::Failed\|App\Enums\TranscriptionStatus::Transcribing and App\Enums\TranscriptionStatus::Draft will always evaluate to true. |
| database/seeders/DatabaseSeeder.php | 235 | match.alwaysTrue | Match arm comparison between App\Enums\TranscriptionStatus::Transcribing and App\Enums\TranscriptionStatus::Transcribing is always true. |
| database/seeders/DatabaseSeeder.php | 259 | variable.undefined | Variable $jobStartedAt might not be defined. |
| database/seeders/DatabaseSeeder.php | 372 | match.alwaysFalse | Match arm comparison between 'mp4' and 'm4a' is always false. |
| database/seeders/DatabaseSeeder.php | 373 | match.alwaysFalse | Match arm comparison between 'mp4' and 'wav' is always false. |
| database/seeders/DatabaseSeeder.php | 379 | match.alwaysFalse | Match arm comparison between 'mp4' and 'm4a' is always false. |
| database/seeders/DatabaseSeeder.php | 380 | match.alwaysFalse | Match arm comparison between 'mp4' and 'wav' is always false. |
| database/seeders/DatabaseSeeder.php | 381 | match.alwaysTrue | Match arm comparison between 'mp4' and 'mp4' is always true. |
| database/seeders/DatabaseSeeder.php | 386 | nullCoalesce.offset | Offset 'folder_id' on array{title: 'Coaching Session', original_filename: 'coaching_session.mp3', media_type: App\Enums\MediaType::Audio, extension: 'mp3', mime_type: 'audio/mpeg', file_size_bytes: 15728640, duration_seconds: 450, status: App\Enums\MediaStatus::Uploaded, ...}\|array{title: 'Scaling System…', original_filename: 'scaling_review.mp4', media_type: App\Enums\MediaType::Video, extension: 'mp4', mime_type: 'video/mp4', file_size_bytes: 104857600, duration_seconds: 2400, status: App\Enums\MediaStatus::Processing, ...}\|array{title: 'Team Huddle', original_filename: 'team_huddle.mp4', media_type: App\Enums\MediaType::Video, extension: 'mp4', mime_type: 'video/mp4', file_size_bytes: 73400320, duration_seconds: 600, status: App\Enums\MediaStatus::Ready, ...}\|array{title: 'Weekly Status Update', original_filename: 'weekly_status.mp3', media_type: App\Enums\MediaType::Audio, extension: 'mp3', mime_type: 'audio/mpeg', file_size_bytes: 26214400, duration_seconds: 900, status: App\Enums\MediaStatus::Ready, ...} on left side of ?? always exists and is not nullable. |
| database/seeders/DatabaseSeeder.php | 408 | identical.alwaysFalse | Strict comparison using === between App\Enums\TranscriptionStatus::Completed\|App\Enums\TranscriptionStatus::Queued\|App\Enums\TranscriptionStatus::Transcribing and App\Enums\TranscriptionStatus::Failed will always evaluate to false. |
| database/seeders/DatabaseSeeder.php | 425 | notIdentical.alwaysTrue | Strict comparison using !== between App\Enums\TranscriptionStatus::Completed\|App\Enums\TranscriptionStatus::Queued\|App\Enums\TranscriptionStatus::Transcribing and App\Enums\TranscriptionStatus::Draft will always evaluate to true. |
| database/seeders/DatabaseSeeder.php | 428 | match.alwaysFalse | Match arm comparison between App\Enums\TranscriptionStatus::Queued\|App\Enums\TranscriptionStatus::Transcribing and App\Enums\TranscriptionStatus::Failed is always false. |
| database/seeders/DatabaseSeeder.php | 444 | nullCoalesce.variable | Variable $jobStartedAt on left side of ?? always exists and is not nullable. |
| database/seeders/DatabaseSeeder.php | 449 | identical.alwaysFalse | Strict comparison using === between App\Enums\ProcessingStatus::Completed\|App\Enums\ProcessingStatus::Queued\|App\Enums\ProcessingStatus::Running and App\Enums\ProcessingStatus::Failed will always evaluate to false. |
| database/seeders/DatabaseSeeder.php | 453 | variable.undefined | Variable $jobStartedAt might not be defined. |
