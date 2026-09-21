<?php

use App\Http\Controllers\TranslationExportController;
use Illuminate\Support\Facades\Route;

/*
 * Translation export routes (P5-007).
 *
 * Kept in a dedicated route file so translation work does not modify the
 * pre-existing dirty `routes/web.php` baseline (BLOCKERS.md B-001/B-002). The
 * file is registered from `bootstrap/app.php` inside the `web` middleware group.
 */

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/translations/{translation}/export/txt', [TranslationExportController::class, 'exportTxt'])
        ->name('translations.export.txt');
    Route::get('/translations/{translation}/export/srt', [TranslationExportController::class, 'exportSrt'])
        ->name('translations.export.srt');
    Route::get('/translations/{translation}/export/vtt', [TranslationExportController::class, 'exportVtt'])
        ->name('translations.export.vtt');
    Route::get('/translations/{translation}/export/docx', [TranslationExportController::class, 'exportDocx'])
        ->name('translations.export.docx');
});
