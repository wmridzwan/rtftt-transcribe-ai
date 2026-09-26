<?php

namespace App\Http\Controllers;

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\TranscriptExperience\SegmentTimestamp;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class TranscriptionExportController extends Controller
{
    /**
     * Resolve the ordered export rows for a transcription (P6-010 revision
     * awareness, frozen P6-001 §9).
     *
     * When a valid active revision exists it is the sole source of truth
     * (ordered by revision `position`, mirroring the workspace projection in
     * `TranscriptionController::displaySegments()`); otherwise the immutable
     * machine source is used unchanged. The machine rows are never mutated.
     *
     * @return list<array{text: string, start: float, end: float}>
     */
    private function exportRows(Transcription $transcription, RevisionService $revisions): array
    {
        $active = $revisions->active(request()->user(), $transcription);

        if ($active !== null && ! $active->isEmpty()) {
            return array_map(static fn (RevisionSegmentData $segment): array => [
                'text' => $segment->text,
                'start' => $segment->startSeconds,
                'end' => $segment->endSeconds,
            ], $active->orderedSegments());
        }

        $rows = [];

        foreach ($transcription->segments()->orderBy('segment_index')->get() as $segment) {
            $rows[] = [
                'text' => $segment->text,
                'start' => (float) $segment->start_seconds,
                'end' => (float) $segment->end_seconds,
            ];
        }

        return $rows;
    }

    public function exportTxt(Transcription $transcription, RevisionService $revisions): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $rows = $this->exportRows($transcription, $revisions);
        $text = $transcription->title."\n\n";

        if ($rows === []) {
            $text .= $transcription->full_text ?? '';
        } else {
            $text .= implode("\n\n", array_column($rows, 'text'));
        }

        $filename = Str::slug($transcription->title).'.txt';

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportSrt(Transcription $transcription, RevisionService $revisions): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $rows = $this->exportRows($transcription, $revisions);
        $srt = '';

        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $start = SegmentTimestamp::fromSeconds($row['start'])->srt();
            $end = SegmentTimestamp::fromSeconds($row['end'])->srt();
            $srt .= "{$number}\n{$start} --> {$end}\n{$row['text']}\n\n";
        }

        $filename = Str::slug($transcription->title).'.srt';

        return response($srt, 200, [
            'Content-Type' => 'application/x-subrip; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportVtt(Transcription $transcription, RevisionService $revisions): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $rows = $this->exportRows($transcription, $revisions);
        $vtt = "WEBVTT\n\n";

        foreach ($rows as $row) {
            $start = SegmentTimestamp::fromSeconds($row['start'])->vtt();
            $end = SegmentTimestamp::fromSeconds($row['end'])->vtt();
            $vtt .= "{$start} --> {$end}\n{$row['text']}\n\n";
        }

        $filename = Str::slug($transcription->title).'.vtt';

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportDocx(Transcription $transcription, RevisionService $revisions): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addTitle($transcription->title, 1);

        $rows = $this->exportRows($transcription, $revisions);

        if ($rows === []) {
            $section->addText($transcription->full_text ?? '');
        } else {
            foreach ($rows as $row) {
                $section->addText($row['text']);
                $section->addText('');
            }
        }

        $filename = Str::slug($transcription->title).'.docx';

        $exportDirectory = sys_get_temp_dir();

        if (! is_dir($exportDirectory) || ! is_writable($exportDirectory)) {
            throw new \RuntimeException('The transcript export directory is unavailable.');
        }

        $tempPath = tempnam($exportDirectory, 'transcript-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to allocate a transcript export file.');
        }

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);

            if ($content === false) {
                throw new \RuntimeException('Unable to read the transcript export file.');
            }
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
