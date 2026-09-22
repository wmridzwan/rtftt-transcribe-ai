<?php

use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionProvider;

function addExportSegment(Transcription $transcription, int $index, float $start, float $end, string $text, string $language = 'en'): void
{
    $transcription->segments()->create([
        'segment_index' => $index,
        'start_seconds' => $start,
        'end_seconds' => $end,
        'text' => $text,
        'language' => $language,
    ]);
}

function completedTranscription(User $user, array $attributes = []): Transcription
{
    return Transcription::factory()->completed()->create(array_merge([
        'user_id' => $user->id,
    ], $attributes));
}

it('exports all four formats for a completed transcription', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user, ['title' => 'All Formats']);
    addExportSegment($transcription, 0, 0, 5, 'Hello.');

    $this->get(route('transcriptions.export.txt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.srt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.docx', $transcription))->assertOk();
});

it('denies export for non-completed transcriptions across all formats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = Transcription::factory()->transcribing()->create(['user_id' => $user->id]);

    $this->get(route('transcriptions.export.txt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.srt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.docx', $transcription))->assertForbidden();
});

it('denies cross-user export', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($other);
    $transcription = completedTranscription($owner);

    $this->get(route('transcriptions.export.txt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.srt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.docx', $transcription))->assertForbidden();
});

it('orders exported segments deterministically by segment_index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user);
    addExportSegment($transcription, 2, 10, 15, 'Third');
    addExportSegment($transcription, 0, 0, 5, 'First');
    addExportSegment($transcription, 1, 5, 10, 'Second');

    $srt = $this->get(route('transcriptions.export.srt', $transcription))->getContent();

    expect(strpos($srt, 'First'))->toBeLessThan(strpos($srt, 'Second'))
        ->and(strpos($srt, 'Second'))->toBeLessThan(strpos($srt, 'Third'));
});

it('preserves multilingual unicode across all formats', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user, ['title' => 'Multilingual']);
    addExportSegment($transcription, 0, 0, 1, 'Selamat datang', 'ms');
    addExportSegment($transcription, 1, 1, 2, 'Welcome', 'en');
    addExportSegment($transcription, 2, 2, 3, '欢迎', 'zh');
    addExportSegment($transcription, 3, 3, 4, 'வணக்கம்', 'ta');
    addExportSegment($transcription, 4, 4, 5, 'und', 'und');

    $txt = $this->get(route('transcriptions.export.txt', $transcription))->getContent();
    $srt = $this->get(route('transcriptions.export.srt', $transcription))->getContent();
    $vtt = $this->get(route('transcriptions.export.vtt', $transcription))->getContent();

    foreach (['Selamat datang', 'Welcome', '欢迎', 'வணக்கம்'] as $needle) {
        expect($txt)->toContain($needle)
            ->and($srt)->toContain($needle)
            ->and($vtt)->toContain($needle);
    }

    $docx = $this->get(route('transcriptions.export.docx', $transcription))->getContent();
    $path = tempnam(sys_get_temp_dir(), 'p4-005-').'.docx';
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $document = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    foreach (['Selamat datang', 'Welcome', '欢迎', 'வணக்கம்'] as $needle) {
        expect($document)->toContain($needle);
    }
});

it('formats millisecond timestamps for SRT and VTT without overflow', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user);
    addExportSegment($transcription, 0, 5.999, 6.999, 'Boundary');

    $srt = $this->get(route('transcriptions.export.srt', $transcription))->getContent();
    $vtt = $this->get(route('transcriptions.export.vtt', $transcription))->getContent();

    expect($srt)->toContain('00:00:05,999 --> 00:00:06,999')
        ->and($vtt)->toContain('00:00:05.999 --> 00:00:06.999')
        ->and($srt)->not->toContain(',1000')
        ->and($vtt)->not->toContain('.1000');
});

it('falls back to full_text for TXT and DOCX when there are no segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user, [
        'title' => 'Fallback',
        'full_text' => 'Fallback transcript content.',
    ]);

    $txt = $this->get(route('transcriptions.export.txt', $transcription))->getContent();
    expect($txt)->toContain('Fallback transcript content.');

    $docx = $this->get(route('transcriptions.export.docx', $transcription))->getContent();
    $path = tempnam(sys_get_temp_dir(), 'p4-005-').'.docx';
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $document = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);
    expect($document)->toContain('Fallback transcript content.');
});

it('exports a valid no-speech completed transcript without error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user, [
        'title' => 'No Speech',
        'speech_detected' => false,
        'full_text' => '',
    ]);

    $txt = $this->get(route('transcriptions.export.txt', $transcription))->getContent();
    $srt = $this->get(route('transcriptions.export.srt', $transcription))->getContent();
    $vtt = $this->get(route('transcriptions.export.vtt', $transcription))->getContent();
    $docx = $this->get(route('transcriptions.export.docx', $transcription))->getContent();

    expect($txt)->toContain('No Speech')
        ->and($srt)->toBe('')
        ->and($vtt)->toStartWith('WEBVTT')
        ->and(strlen($docx))->toBeGreaterThan(0);
});

it('keeps export filenames and content types correct', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = completedTranscription($user, ['title' => 'My Export']);
    addExportSegment($transcription, 0, 0, 1, 'Text.');

    $this->get(route('transcriptions.export.txt', $transcription))
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="my-export.txt"');
    $this->get(route('transcriptions.export.srt', $transcription))
        ->assertHeader('Content-Type', 'application/x-subrip; charset=utf-8');
    $this->get(route('transcriptions.export.vtt', $transcription))
        ->assertHeader('Content-Type', 'text/vtt; charset=utf-8');
    $this->get(route('transcriptions.export.docx', $transcription))
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('never invokes the transcription provider during export', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $provider = $this->mock(TranscriptionProvider::class);
    $provider->shouldNotReceive('transcribe');

    $transcription = completedTranscription($user);
    addExportSegment($transcription, 0, 0, 1, 'Text.');

    $this->get(route('transcriptions.export.txt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.srt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertOk();
    $this->get(route('transcriptions.export.docx', $transcription))->assertOk();
});
