<?php

namespace App\Http\Controllers;

use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class TranscriptionExportController extends Controller
{
    public function exportTxt(Transcription $transcription): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $segments = $transcription->segments()->orderBy('segment_index')->get();
        $text = $transcription->title."\n\n";

        if ($segments->isEmpty()) {
            $text .= $transcription->full_text ?? '';
        } else {
            $text .= $segments->pluck('text')->implode("\n\n");
        }

        $filename = Str::slug($transcription->title).'.txt';

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportSrt(Transcription $transcription): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $segments = $transcription->segments()->orderBy('segment_index')->get();
        $srt = '';

        foreach ($segments as $index => $segment) {
            $number = $index + 1;
            $start = $this->formatSrtTime($segment->start_seconds);
            $end = $this->formatSrtTime($segment->end_seconds);
            $srt .= "{$number}\n{$start} --> {$end}\n{$segment->text}\n\n";
        }

        $filename = Str::slug($transcription->title).'.srt';

        return response($srt, 200, [
            'Content-Type' => 'application/x-subrip; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportVtt(Transcription $transcription): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $segments = $transcription->segments()->orderBy('segment_index')->get();
        $vtt = "WEBVTT\n\n";

        foreach ($segments as $segment) {
            $start = $this->formatVttTime($segment->start_seconds);
            $end = $this->formatVttTime($segment->end_seconds);
            $vtt .= "{$start} --> {$end}\n{$segment->text}\n\n";
        }

        $filename = Str::slug($transcription->title).'.vtt';

        return response($vtt, 200, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportDocx(Transcription $transcription): Response
    {
        $this->authorize('view', $transcription);

        if ($transcription->status !== TranscriptionStatus::Completed) {
            abort(403, 'Only completed transcriptions can be exported.');
        }

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addTitle($transcription->title, 1);

        $segments = $transcription->segments()->orderBy('segment_index')->get();

        if ($segments->isEmpty()) {
            $section->addText($transcription->full_text ?? '');
        } else {
            foreach ($segments as $segment) {
                $section->addText($segment->text);
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

    private function formatSrtTime(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d,000', $hours, $minutes, $seconds);
    }

    private function formatVttTime(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d.000', $hours, $minutes, $seconds);
    }
}
