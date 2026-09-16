<?php

namespace App\Providers;

use App\Transcription\HttpTranscriptionProvider;
use App\Transcription\TranscriptionProvider;
use Illuminate\Support\ServiceProvider;

class TranscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranscriptionProvider::class, function () {
            return new HttpTranscriptionProvider(
                workerBaseUrl: config('transcription.worker_url', 'http://localhost:8000'),
                bearerToken: config('transcription.worker_token', ''),
            );
        });
    }
}
