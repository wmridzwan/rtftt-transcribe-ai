<?php

use App\Http\Controllers\TranslationActionController;
use App\Http\Controllers\TranslationExportController;
use App\Http\Controllers\TranslationWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
 * Translation workspace and export routes (P5-006, P5-007).
 *
 * Kept in a dedicated route file so translation work does not modify the
 * pre-existing dirty `routes/web.php` baseline (BLOCKERS.md B-001/B-002). The
 * file is registered from `bootstrap/app.php` inside the `web` middleware group.
 */

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transcriptions/{transcription}/translations', [TranslationWorkspaceController::class, 'show'])
        ->name('transcriptions.translations.show');
    Route::post('/transcriptions/{transcription}/translations', [TranslationActionController::class, 'store'])
        ->name('transcriptions.translations.store');
    Route::get('/translations/{translation}/status', [TranslationWorkspaceController::class, 'status'])
        ->name('translations.status');
    Route::post('/translations/{translation}/retry', [TranslationActionController::class, 'retry'])
        ->name('translations.retry');

    Route::get('/translations/{translation}/export/txt', [TranslationExportController::class, 'exportTxt'])
        ->name('translations.export.txt');
    Route::get('/translations/{translation}/export/srt', [TranslationExportController::class, 'exportSrt'])
        ->name('translations.export.srt');
    Route::get('/translations/{translation}/export/vtt', [TranslationExportController::class, 'exportVtt'])
        ->name('translations.export.vtt');
    Route::get('/translations/{translation}/export/docx', [TranslationExportController::class, 'exportDocx'])
        ->name('translations.export.docx');
});
