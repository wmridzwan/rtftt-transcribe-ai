# RTFTT Transcribe AI Architecture

## Current Phase 1 Architecture

```
Browser
   ↓
Laravel Application
   ↓
Livewire / Blade / Flux UI
   ↓
Application Models (Eloquent)
   ↓
SQLite (Development)
```

### Key Components

- **Authentication**: Laravel Fortify (login, password reset, password confirmation)
- **Frontend**: Livewire 4 + Blade + Flux UI + Tailwind CSS 4
- **Database**: SQLite for development
- **Testing**: Pest
- **Build Tool**: Vite

### Domain Models

- **User** — Application users with roles (admin/user)
- **MediaFile** — Uploaded/source media assets
- **Transcription** — Transcription attempts/results associated with media
- **TranscriptionSegment** — Timestamped transcript chunks
- **ProcessingJob** — Processing lifecycle/stage information

### Important Design Decisions

1. MediaFile, Transcription, and ProcessingJob are intentionally separate models
2. One MediaFile may have multiple Transcription attempts in the future
3. Different models, languages, and processing jobs may apply to the same media
4. Phase 1 creates prototype domain data without real processing

## Future Architecture

```
Browser
   ↓
Laravel Application
   ↓
Queue (Redis / Horizon)
   ↓
Independent Transcription Worker
   ↓
faster-whisper (CPU + INT8 → GPU + CUDA + FP16)
   ↓
Database / Result Callback
```

### Future Deployment Target

```
Laravel App VPS
      ↓
Redis / Queue
      ↓
CPU or GPU Worker(s)
```

### Key Principles

- The transcription worker must eventually be independently deployable
- Never perform heavy transcription synchronously inside an HTTP request
- Laravel should not care which compute backend processes the transcription
- Future worker metrics: audio_duration_seconds, processing_seconds, model, worker, device, RTF
