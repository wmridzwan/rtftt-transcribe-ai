# RTFTT Transcribe AI

Audio/video transcription management platform.

## Current Phase

Phase 1 — Application Foundation + Full Clickable Prototype

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
