<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoTranscriptionController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\MediaActionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaUploadController;
use App\Http\Controllers\ProcessingJobController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TranscriptionActionController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\TranscriptionExportController;
use App\Http\Controllers\TranscriptRevisionController;
use App\Livewire\Folders\Index as FoldersIndex;
use App\Livewire\Media\Show as MediaShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transcriptions', [TranscriptionController::class, 'index'])->name('transcriptions.index');
    Route::get('/transcriptions/create', [TranscriptionController::class, 'create'])->name('transcriptions.create');
    Route::post('/transcriptions', [DemoTranscriptionController::class, 'store'])->name('transcriptions.store');
    Route::get('/transcriptions/{transcription}', [TranscriptionController::class, 'show'])->name('transcriptions.show');

    Route::patch('/transcriptions/{transcription}/rename', [TranscriptionActionController::class, 'rename'])->name('transcriptions.rename');
    Route::post('/transcriptions/{transcription}/retry', [TranscriptionActionController::class, 'retry'])->name('transcriptions.retry');
    Route::delete('/transcriptions/{transcription}', [TranscriptionActionController::class, 'destroy'])->name('transcriptions.destroy');

    // P6-003 text editing + undo/redo (append-only revision layer).
    Route::post('/transcriptions/{transcription}/revisions', [TranscriptRevisionController::class, 'store'])->name('transcriptions.revisions.store');
    Route::post('/transcriptions/{transcription}/revisions/timing', [TranscriptRevisionController::class, 'timing'])->name('transcriptions.revisions.timing');
    Route::post('/transcriptions/{transcription}/revisions/split', [TranscriptRevisionController::class, 'split'])->name('transcriptions.revisions.split');
    Route::post('/transcriptions/{transcription}/revisions/merge', [TranscriptRevisionController::class, 'merge'])->name('transcriptions.revisions.merge');
    Route::post('/transcriptions/{transcription}/revisions/undo', [TranscriptRevisionController::class, 'undo'])->name('transcriptions.revisions.undo');
    Route::post('/transcriptions/{transcription}/revisions/redo', [TranscriptRevisionController::class, 'redo'])->name('transcriptions.revisions.redo');

    Route::get('/transcriptions/{transcription}/export/txt', [TranscriptionExportController::class, 'exportTxt'])->name('transcriptions.export.txt');
    Route::get('/transcriptions/{transcription}/export/srt', [TranscriptionExportController::class, 'exportSrt'])->name('transcriptions.export.srt');
    Route::get('/transcriptions/{transcription}/export/vtt', [TranscriptionExportController::class, 'exportVtt'])->name('transcriptions.export.vtt');
    Route::get('/transcriptions/{transcription}/export/docx', [TranscriptionExportController::class, 'exportDocx'])->name('transcriptions.export.docx');

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/upload', [MediaUploadController::class, 'create'])->name('media.upload');
    Route::post('/media/upload', [MediaUploadController::class, 'store'])->name('media.upload.store');
    Route::get('/media/{mediaFile}', MediaShow::class)->name('media.show');
    Route::patch('/media/{mediaFile}/rename', [MediaActionController::class, 'rename'])->name('media.rename');
    Route::delete('/media/{mediaFile}', [MediaActionController::class, 'destroy'])->name('media.destroy');
    Route::get('/media/{mediaFile}/download', [MediaActionController::class, 'download'])->name('media.download');
    Route::get('/media/{mediaFile:uuid}/stream', [MediaActionController::class, 'stream'])->name('media.stream');
    Route::patch('/media/{mediaFile}/move', [MediaActionController::class, 'moveToFolder'])->name('media.move');

    Route::get('/folders', FoldersIndex::class)->name('folders.index');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('/folders/{folder}', [FolderController::class, 'show'])->name('folders.show');
    Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('/jobs', [ProcessingJobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{processingJob}', [ProcessingJobController::class, 'show'])->name('jobs.show');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
});

require __DIR__.'/settings.php';
