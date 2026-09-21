<?php

namespace App\Http\Controllers;

use App\Models\Translation;
use App\TranscriptExperience\SegmentTimestamp;
use App\Translation\TranslationStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Translated transcript export (P5-007, ADR-022 D5-08).
 *
 * Reads persisted translated segments only: it never invokes the translation
 * provider, never recomputes timestamps, and never overwrites a source export.
 */
class TranslationExportController extends Controller
{
    public function exportTxt(Translation $translation): Response
    {
        $this->authorizeExport($translation);

        $segments = $translation->segments()->orderBy('segment_index')->get();
        $text = $translation->transcription->title."\n\n";

        if ($segments->isEmpty()) {
            $text .= $translation->full_text ?? '';
        } else {
            $text .= $segments->pluck('text')->implode("\n\n");
        }

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($translation, 'txt').'"',
        ]);
    }

    public function exportSrt(Translation $translation): Response
    {
        $this->authorizeExport($translation);

        $segments = $translation->segments()->orderBy('segment_index')->get();
        $srt = '';

        foreach ($segments as $index => $segment) {
            $number = $index + 1;
            $start = SegmentTimestamp::fromSeconds($segment->start_seconds)->srt();
            $end = SegmentTimestamp::fromSeconds($segment->end_seconds)->srt();
            $srt .= "{$number}\n{$start} --> {$end}\n{$segment->text}\n\n";
        }

        return response($srt, 200, [
            'Content-Type' => 'application/x-subrip; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($translation, 'srt').'"',
        ]);
    }

    public function exportVtt(Translation $translation): Response
    {
        $this->authorizeExport($translation);

        $segments = $translation->segments()->orderBy('segment_index')->get();
        $vtt = "WEBVTT\n\n";

        foreach ($segments as $segment) {
            $start = SegmentTimestamp::fromSeconds($segment->start_seconds)->vtt();
            $end = SegmentTimestamp::fromSeconds($segment->end_seconds)->vtt();
            $vtt .= "{$start} --> {$end}\n{$segment->text}\n\n";
        }

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($translation, 'vtt').'"',
        ]);
    }

    public function exportDocx(Translation $translation): Response
    {
        $this->authorizeExport($translation);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addTitle($translation->transcription->title, 1);

        $segments = $translation->segments()->orderBy('segment_index')->get();

        if ($segments->isEmpty()) {
            $section->addText($translation->full_text ?? '');
        } else {
            foreach ($segments as $segment) {
                $section->addText($segment->text);
                $section->addText('');
            }
        }

        $exportDirectory = sys_get_temp_dir();

        if (! is_dir($exportDirectory) || ! is_writable($exportDirectory)) {
            throw new \RuntimeException('The transcript export directory is unavailable.');
        }

        $tempPath = tempnam($exportDirectory, 'translation-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to allocate a translation export file.');
        }

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);

            if ($content === false) {
                throw new \RuntimeException('Unable to read the translation export file.');
            }
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($translation, 'docx').'"',
        ]);
    }

    private function authorizeExport(Translation $translation): void
    {
        $this->authorize('view', $translation->transcription);

        if ($translation->status !== TranslationStatus::Completed) {
            abort(403, 'Only completed translations can be exported.');
        }
    }

    private function filename(Translation $translation, string $extension): string
    {
        $title = Str::slug($translation->transcription->title);

        return $title.'-'.$translation->target_language->value.'.'.$extension;
    }
}
