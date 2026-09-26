<?php

namespace App\Providers;

use App\Backup\BackupManager;
use App\Deployment\ProductionConfigGuard;
use App\Deployment\ProductionPostureChecks;
use App\Editing\Persistence\EloquentRevisionRepository;
use App\Editing\Persistence\EloquentTranslationStalenessWriter;
use App\Editing\RevisionRepository;
use App\Editing\TranslationStalenessWriter;
use App\Models\MediaFile;
use App\Security\SecurityAuditLog;
use App\Transcription\TranscriptionQueueConfig;
use App\Translation\HttpTranslationProvider;
use App\Translation\TranslationProvider;
use App\Translation\TranslationQueueConfig;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(TranslationStalenessWriter::class, EloquentTranslationStalenessWriter::class);

        // P7-007: the backup manager needs the media disk adapter, which
        // the container cannot auto-wire.
        $this->app->bind(BackupManager::class, fn (): BackupManager => BackupManager::forMediaDisk());

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
        TranscriptionQueueConfig::assertConsistent();
        ProductionConfigGuard::assertValid();
        ProductionPostureChecks::assertValid();
        $this->configureRateLimiters();
        $this->configureSecurityAudit();
        $this->configureDefaults();
    }

    /**
     * Named rate limiters (P7-006, single-admin posture). Thresholds live
     * in config/security.php so limits are documented and adjustable.
     */
    protected function configureRateLimiters(): void
    {
        // Thresholds read at request time (not boot time) so tests and
        // runtime config changes apply without re-booting the provider.
        RateLimiter::for('login', function (Request $request): Limit {
            [$max, $decay] = array_map('intval', (array) config('security.throttles.login', [5, 1]));

            return Limit::perMinutes($decay, $max)->by($request->input('email').'|'.$request->ip());
        });

        RateLimiter::for('upload-initiate', function (Request $request): Limit {
            [$max, $decay] = array_map('intval', (array) config('security.throttles.upload_initiate', [30, 1]));

            $key = $request->user() !== null ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip();

            return Limit::perMinutes($decay, $max)->by($key);
        });

        RateLimiter::for('csp-report', function (Request $request): Limit {
            [$max, $decay] = array_map('intval', (array) config('security.throttles.csp_report', [60, 1]));

            return Limit::perMinutes($decay, $max)->by('csp:'.$request->ip());
        });
    }

    /**
     * Failed-login audit events (P7-006 AC6). Credential values never
     * enter the log — email domain shape only.
     */
    protected function configureSecurityAudit(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;
            $domain = null;

            if (is_string($email) && str_contains($email, '@')) {
                $afterAt = strrchr($email, '@');
                $domain = $afterAt === false ? null : substr($afterAt, 1);
            }

            SecurityAuditLog::record('auth_failure', [
                'guard' => $event->guard,
                'email_domain' => $domain,
            ]);
        });
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
