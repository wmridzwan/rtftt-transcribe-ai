<?php

namespace App\Providers;

use App\Transcription\TranscriptionProvider;
use App\Transcription\TranscriptionProviderResolver;
use Illuminate\Support\ServiceProvider;

class TranscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // PP-T2: sole resolver call site. Re-evaluated on every interface
        // resolution (bind, not singleton) so selection and kill-switch
        // changes apply without redeploying code.
        $this->app->bind(TranscriptionProvider::class, fn () => (new TranscriptionProviderResolver)->resolve());
    }

    public function boot(): void
    {
        TranscriptionProviderResolver::validateSelection();
    }
}
