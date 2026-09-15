# Voxora / RTFTT Transcribe AI

Audio/video transcription management platform.

Voxora is the long-term product and brand. RTFTT is the current
engineering/repository identity; the repository is not renamed by this
documentation change.

## Current Phase

Phase 2 checkpoint — P2-001/P2-002 and completed follow-up contracts.

Phase 1 — Application Foundation + Full Clickable Prototype — is accepted.
P2-003 and later remain unauthorized pending a separate continuation decision.

## Technology Stack

- **Backend**: Laravel 13 + PHP 8.4
- **Frontend**: Livewire 4 + Blade + Flux UI + Tailwind CSS 4
- **Auth**: Laravel Fortify
- **Database**: SQLite (development)
- **Testing**: Pest
- **Build**: Vite
- **IDE Support**: Laravel Boost / OpenCode

## Local Requirements

- PHP 8.3+
- Node.js 18+
- Composer
- Laravel Herd (recommended)

### FFprobe / FFmpeg for media metadata probing

The bounded P2-005 metadata probe requires the `ffprobe` executable from an
FFmpeg installation. It is used only to extract technical metadata such as
duration, codecs, sample rate, and channel count from an already persisted
private media file; it does not enable transcription or other Phase 3
processing.

By default the application runs `ffprobe` from `PATH`. Set
`RTFTT_FFPROBE_PATH` in the environment when the executable is installed at a
non-standard location. Verify availability with `ffprobe -version` (or the
configured path) and with `php artisan test --compact
tests/Feature/MediaMetadataProbeServiceTest.php`. The real fixture test is
explicitly skipped with a reason when FFprobe is unavailable; the application
probe service safely leaves metadata null and logs the failure rather than
failing or deleting the uploaded media.

## Installation

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
```

Or use the setup script:

```bash
composer setup
```

## Running Locally

### With Laravel Herd

Simply access the application through Herd's local domain.

### With Composer Dev Script

```bash
composer dev
```

This starts the server, queue listener, and Vite dev server concurrently.

## Database

### Setup

```bash
php artisan migrate:fresh --seed
```

### Seed Users

| Role  | Email              | Password |
|-------|--------------------|----------|
| Admin | admin@rtftt.local  | password |
| User  | user@rtftt.local   | password |

## Testing

```bash
php artisan test --compact
```

## Frontend Build

```bash
npm run build
```

For development with hot reload:

```bash
npm run dev
```

## Phase 1 Limitations

This is a clickable prototype. The following are NOT yet implemented:

- Real file uploads
- FFmpeg / FFprobe media processing
- Actual transcription with faster-whisper
- Queue worker processing
- Production deployment
- Billing / subscriptions
- Public registration

## Future Architecture

```
Browser → Laravel → Queue → Transcription Worker → faster-whisper → Database
```

The transcription worker will eventually be independently deployable on CPU or GPU infrastructure.
