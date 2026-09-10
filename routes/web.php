<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoTranscriptionController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\MediaActionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProcessingJobController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TranscriptionActionController;
use App\Http\Controllers\TranscriptionController;
use App\Http\Controllers\TranscriptionExportController;
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
    Route::delete('/transcriptions/{transcription}', [TranscriptionActionController::class, 'destroy'])->name('transcriptions.destroy');

    Route::get('/transcriptions/{transcription}/export/txt', [TranscriptionExportController::class, 'exportTxt'])->name('transcriptions.export.txt');
    Route::get('/transcriptions/{transcription}/export/srt', [TranscriptionExportController::class, 'exportSrt'])->name('transcriptions.export.srt');
    Route::get('/transcriptions/{transcription}/export/vtt', [TranscriptionExportController::class, 'exportVtt'])->name('transcriptions.export.vtt');
    Route::get('/transcriptions/{transcription}/export/docx', [TranscriptionExportController::class, 'exportDocx'])->name('transcriptions.export.docx');

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{mediaFile}', MediaShow::class)->name('media.show');
    Route::patch('/media/{mediaFile}/rename', [MediaActionController::class, 'rename'])->name('media.rename');
    Route::delete('/media/{mediaFile}', [MediaActionController::class, 'destroy'])->name('media.destroy');
    Route::get('/media/{mediaFile}/download', [MediaActionController::class, 'download'])->name('media.download');
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
