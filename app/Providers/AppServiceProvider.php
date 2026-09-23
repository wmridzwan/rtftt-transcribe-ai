<?php

namespace App\Providers;

use App\Editing\Persistence\EloquentRevisionRepository;
use App\Editing\RevisionRepository;
use App\Models\MediaFile;
use App\Translation\HttpTranslationProvider;
use App\Translation\TranslationProvider;
use App\Translation\TranslationQueueConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RevisionRepository::class, EloquentRevisionRepository::class);

        $this->app->singleton(TranslationProvider::class, function () {
            return new HttpTranslationProvider(
                workerBaseUrl: (string) config('translation.worker_url', 'http://localhost:8000'),
                bearerToken: (string) config('translation.worker_token', ''),
                providerName: (string) config('translation.provider', 'self-hosted'),
                model: (string) config('translation.model', 'self-hosted-default'),
                contractVersion: (string) config('translation.contract_version', '1.0'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MediaFile::assertPrivateStorageDisk();
        TranslationQueueConfig::assertConsistent();
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
