# RTFTT Transcribe AI — Deep Project Context

## 1. What This Project Is

RTFTT Transcribe AI is a web-based AI transcription and translation platform for recorded and eventually live spoken content.

The core purpose of the application is to turn spoken audio/video into accurate, readable, searchable text and later translate that transcript into another language.

The product is especially intended for real-world multilingual speech such as:

- English
- Bahasa Melayu
- Mandarin / Chinese
- mixed English + Chinese
- mixed Bahasa Melayu + English
- meetings where speakers naturally switch languages

A common real-world example is a recording where a speaker explains something primarily in Mandarin but mixes English terminology, product names, technical terms, or business vocabulary.

The system should preserve the meaning of the original speech while producing a useful transcript and, when requested, a natural translation.

The long-term product direction is:

Audio / Video / Live Speech
        ↓
Speech Recognition
        ↓
Timestamped Transcript
        ↓
Translation / AI Processing
        ↓
Readable, Searchable and Exportable Result

RTFTT Transcribe AI should eventually feel like a focused combination of transcription tools such as Notta-style applications and AI-assisted translation/summary tools, while remaining under our own control and architecture.

---

# 2. Primary Product Goal

The primary goal is NOT simply:

"upload a file and call an AI API."

The goal is to build a reusable transcription platform.

The application should eventually support two major modes.

## Mode A — Recorded Transcription

User provides an existing:

- MP3
- M4A
- WAV
- MP4
- MOV
- other supported audio/video format

The system processes the media and produces a timestamped transcript.

Conceptually:

User
  ↓
Upload Recording
  ↓
Media Processing
  ↓
Speech-to-Text
  ↓
Transcript
  ↓
Translation / AI features
  ↓
Review / Search / Export

This is the FIRST implementation priority.

---

## Mode B — Live Transcription

Later, the application should support real-time or near-real-time transcription.

Possible sources include:

- microphone
- meeting
- seminar
- training session
- interview
- lecture
- coaching session
- presentation
- live discussion

Conceptually:

Microphone / Live Audio
        ↓
Audio chunks / stream
        ↓
Transcription worker
        ↓
Partial transcript
        ↓
Final transcript segments
        ↓
Optional live translation
        ↓
Browser UI

Live transcription is a FUTURE feature.

Do not prematurely design Phase 1 around WebSockets, streaming audio or live transcription.

The architecture should simply avoid making live transcription impossible later.

---

# 3. Transcription Is the Core Domain

The most important domain object in the product is the transcription.

A transcription represents the textual interpretation of a media recording.

A transcription may eventually contain:

- source media
- original language
- detected language
- transcription model
- full transcript
- timestamped segments
- speakers
- processing information
- translated transcript
- summary
- processing metrics
- exports

However, these capabilities should be introduced progressively.

Do not create all future features prematurely.

---

# 4. MediaFile and Transcription Are Different Concepts

A very important architecture decision is that:

MediaFile != Transcription

MediaFile represents the source recording.

Example:

weekly-management-meeting.mp4

Transcription represents a transcription attempt/result produced from that media.

Conceptually:

MediaFile
   |
   +---- Transcription A
   |
   +---- Transcription B (possible future reprocessing)

This separation matters because eventually the same recording might be processed:

- using another Whisper model
- using another language configuration
- after a failed attempt
- using a better worker
- after transcription improvements

Therefore do NOT collapse media and transcription into a single database entity.

---

# 5. Timestamped Transcript Segments

The transcript should not exist only as one giant block of text.

The system should store timestamped segments.

Example:

00:00
Good morning everyone.

00:04
Today we're going to review last week's performance.

00:10
首先我们来看一下这个星期的数据。

00:17
The conversion rate increased by approximately twelve percent.

Conceptually:

Transcription
     ↓
TranscriptionSegment
     ↓
start time
end time
text

This enables future capabilities such as:

- click timestamp → seek audio
- synchronized playback
- transcript search
- subtitle generation
- speaker diarization
- segment translation
- transcript editing

---

# 6. Multilingual Speech Is Important

RTFTT Transcribe AI should be designed with multilingual speech in mind from the beginning.

Do not assume:

one recording = one perfectly consistent language.

Real recordings may contain code-switching.

Example:

"今天我们主要讨论这个 marketing strategy，然后下个星期我们 launch the new campaign."

A useful transcription should preserve what was actually spoken rather than unnecessarily forcing everything into one language.

Therefore distinguish between:

SOURCE TRANSCRIPTION

and:

TRANSLATION

The original transcript should represent the original spoken content.

Translation should be a separate transformation.

---

# 7. Translation Product Direction

Translation is a FUTURE phase, but it is a major product capability.

Example:

Original transcript:

"今天我们主要讨论这个 marketing strategy."

Possible Bahasa Melayu translation:

"Hari ini kita akan fokus membincangkan strategi pemasaran ini."

The translation should prioritize:

- meaning
- context
- natural language
- readability

rather than mechanical word-for-word translation.

For Bahasa Melayu specifically, the desired style should generally be natural Bahasa Melayu Malaysia rather than overly literal or unnatural machine-translated Malay.

Depending on product settings, technical terms or common English terminology may reasonably remain in English.

Translation must remain separate from the original transcript so users can always refer back to what was actually spoken.

Possible future structure:

Transcription
     ↓
Original Transcript
     ↓
Translation
     ↓
Malay / English / Chinese / etc.

Do NOT implement translation until explicitly authorized.

---

# 8. Speech Recognition Engine

The planned speech recognition engine is:

faster-whisper

The intention is to self-host transcription rather than permanently depend on a third-party transcription API.

Possible Whisper models to evaluate include:

- small
- medium
- large-v3-turbo

The actual production model must eventually be selected based on benchmarks involving:

- transcription accuracy
- multilingual accuracy
- Chinese/English mixed speech
- processing speed
- CPU usage
- GPU usage
- memory usage
- cost

Do not assume that the largest model is automatically the correct production choice.

Benchmark first.

---

# 9. Initial Compute Strategy

The intended early infrastructure is CPU-first.

Example:

4–8 vCPU
8–16 GB RAM

faster-whisper configuration may initially resemble:

WHISPER_MODEL=large-v3-turbo
WHISPER_DEVICE=cpu
WHISPER_COMPUTE_TYPE=int8

The actual model should be benchmarked.

If transcription demand increases, the worker can later move to GPU infrastructure:

WHISPER_DEVICE=cuda
WHISPER_COMPUTE_TYPE=float16

The Laravel application should NOT care whether transcription is performed on CPU or GPU.

That is the responsibility of the transcription worker.

---

# 10. Critical Architecture Principle

Never tightly couple heavy transcription execution to a web request.

BAD architecture:

Browser
   ↓
Laravel Controller
   ↓
exec("python whisper.py")
   ↓
wait 20 minutes
   ↓
HTTP response

Do NOT build this.

Transcription can take minutes or potentially much longer.

Instead use asynchronous processing.

Target architecture:

Browser
   ↓
Laravel Application
   ↓
Queue
   ↓
Transcription Worker
   ↓
faster-whisper
   ↓
Database / Result
   ↓
Browser

The user submits work and the application tracks processing state.

---

# 11. Laravel's Responsibility

Laravel is the main application/control plane.

Laravel should be responsible for:

- authentication
- users
- authorization
- media metadata
- transcription records
- transcript storage
- processing status
- job orchestration
- UI
- API/control endpoints
- storage coordination
- queue dispatching
- result presentation

Laravel should NOT itself become the speech-recognition engine.

---

# 12. Transcription Worker's Responsibility

The transcription worker should eventually be an independent service/process.

Its responsibility will include:

- receive transcription work
- obtain normalized audio
- load faster-whisper
- execute transcription
- produce timestamped segments
- report processing results
- report errors
- report performance metrics

The worker should be independently deployable.

Initially:

Laravel + worker may live on the SAME VPS.

Later:

Laravel VPS
     ↓
Queue
     ↓
GPU Worker

Later still:

Laravel
   ↓
Queue
   ├── GPU Worker 1
   ├── GPU Worker 2
   └── GPU Worker N

The web application should require minimal changes when worker infrastructure changes.

---

# 13. Media Processing

Before transcription, uploaded media may need normalization.

Future flow:

Uploaded Media
      ↓
FFprobe
      ↓
Inspect metadata
      ↓
FFmpeg
      ↓
Extract / normalize audio
      ↓
Temporary transcription audio
      ↓
Whisper Worker

For video:

MP4 / MOV
    ↓
FFmpeg
    ↓
Audio stream
    ↓
Normalized temporary audio
    ↓
Transcription

The video itself should not be passed unnecessarily through the speech model.

After processing, temporary normalized audio should eventually be cleaned up according to the application's retention strategy.

This is NOT part of Phase 1.

---

# 14. Processing Lifecycle

Transcription is not a single instantaneous action.

It has stages.

Conceptually:

UPLOADED
   ↓
QUEUED
   ↓
PREPARING
   ↓
PROBING MEDIA
   ↓
EXTRACTING AUDIO
   ↓
TRANSCRIBING
   ↓
FINALIZING
   ↓
COMPLETED

Possible failure:

ANY STAGE
   ↓
FAILED

The UI should eventually communicate this lifecycle clearly to users.

ProcessingJob exists to help represent processing activity.

ProcessingJob is a DOMAIN MODEL.

It is NOT the same concept as a Laravel framework Queue Job class.

---

# 15. Processing Metrics

Performance metrics are important because self-hosted transcription has real infrastructure cost.

Important future measurements include:

audio_duration_seconds

processing_seconds

model

worker

device

Real-Time Factor (RTF)

RTF formula:

processing_seconds / audio_duration_seconds

Example:

60 minute recording
processed in 15 minutes

RTF:

15 / 60 = 0.25

Interpretation:

RTF < 1
→ faster than real time

RTF = 1
→ approximately real time

RTF > 1
→ slower than real time

These measurements will help decide:

- which Whisper model to use
- CPU vs GPU
- VPS sizing
- worker scaling
- cost per transcription hour

---

# 16. Storage Philosophy

Do not store large media binaries directly inside the relational database.

Database:

metadata
transcription
segments
processing information

Storage system:

audio
video
temporary normalized audio

During early development, local/VPS storage may be sufficient.

Future production may use S3-compatible storage such as:

- Amazon S3
- Cloudflare R2
- another compatible object storage provider

The storage backend should eventually be replaceable without changing core transcription domain logic.

---

# 17. Privacy and Security

Recordings and transcripts may contain private conversations.

Therefore privacy is a first-class concern.

Important principles:

- authentication required
- private media storage
- ownership isolation
- authorization
- unpredictable/UUID-based storage identifiers
- validate uploaded media
- file-size limits
- controlled download access
- temporary file cleanup
- rate limiting
- safe error handling

A normal user must never be able to access another user's recording or transcript by changing an ID in the URL.

Admin privileges should be explicit.

---

# 18. User Roles

Initial roles:

admin
user

Initial deployment may only be used by the administrator.

Public registration is intentionally disabled.

Later, the application may support multiple users.

Admin may see:

- all transcriptions
- all media
- processing jobs
- worker/system status

Normal users should see only:

- their media
- their transcriptions
- their settings

Do not build organizations, teams or complex RBAC until required.

---

# 19. Transcript User Experience

The eventual transcript screen should become one of the most important screens in the application.

Future desired experience:

┌───────────────────────────────────────────────────────────┐
│ Weekly Management Meeting                                │
│ Completed • Chinese • large-v3-turbo                     │
├───────────────────────┬───────────────────────────────────┤
│                       │ 00:00  Good morning everyone...   │
│    AUDIO / VIDEO      │                                   │
│       PLAYER          │ 00:07  今天我们首先来看...          │
│                       │                                   │
│                       │ 00:18  The sales number...        │
│                       │                                   │
├───────────────────────┴───────────────────────────────────┤
│ Search transcript     Copy     TXT     SRT     VTT       │
└───────────────────────────────────────────────────────────┘

Future interactions may include:

- click timestamp to seek media
- synchronized transcript highlighting
- search
- copy
- edit
- TXT export
- SRT export
- VTT export

Do not implement all of these prematurely.

---

# 20. Future Translation UX

Translation should eventually allow users to distinguish clearly between:

Original

and:

Translated

Possible UI:

[ Original ] [ Bahasa Melayu ]

or side-by-side:

Original Transcript | Bahasa Melayu

The original transcript must never be destroyed when translation is generated.

Translation is derived data.

---

# 21. Future AI Capabilities

Once reliable transcription exists, possible future AI features include:

- summary
- key points
- action items
- chapters
- meeting notes
- Q&A
- transcript chat
- topic extraction
- translation
- speaker analysis

These are downstream features.

The product priority is:

FIRST:
Reliable transcription pipeline.

THEN:
Excellent transcript UX.

THEN:
Translation.

THEN:
Additional AI intelligence.

Do not reverse this priority.

---

# 22. Live Transcription — Future Architecture

Live transcription is an eventual product direction.

A possible architecture is:

Microphone
    ↓
Browser
    ↓
Audio chunks / streaming connection
    ↓
Live transcription service
    ↓
Partial transcript
    ↓
Final transcript segments
    ↓
Laravel persistence
    ↓
Live UI

Possible technologies may eventually include:

- WebSocket
- streaming HTTP
- browser MediaRecorder
- dedicated realtime worker

Technology choice should be evaluated when that phase begins.

Do NOT introduce realtime infrastructure simply because it might be needed later.

---

# 23. Live Translation — Future Direction

Once live transcription is reliable:

Live Speech
    ↓
Partial / Final Transcript
    ↓
Translation Engine
    ↓
Translated Live Transcript

However, live translation introduces additional problems:

- latency
- incomplete sentences
- context
- corrections
- code-switching
- translation stability

Therefore live translation should be treated as a separate engineering challenge rather than simply calling translation for every audio chunk.

---

# 24. Product Development Philosophy

RTFTT Transcribe AI should be built incrementally.

The project does NOT follow:

"Build every planned feature now."

Instead:

Foundation
   ↓
Recorded Upload
   ↓
Media Processing
   ↓
Transcription Engine
   ↓
Reliable End-to-End Pipeline
   ↓
Transcript UX
   ↓
Translation
   ↓
Advanced AI
   ↓
Live Transcription
   ↓
Scale / Production Optimization

Every phase should produce something testable.

---

# 25. Development Workflow

All agents working on this repository should respect:

PLAN
  ↓
BUILD
  ↓
TEST
  ↓
USER REVIEW
  ↓
REFINE
  ↓
COMMIT
  ↓
NEXT MODULE / PHASE

Do not skip USER REVIEW.

Do not automatically move to future phases.

Do not automatically implement something simply because it appears in this document.

This document describes PRODUCT DIRECTION.

Authorization to implement functionality comes from plan.md and the user's current instruction.

This distinction is critical.

---

# 26. Source of Truth Hierarchy

When an AI coding agent works on the repository, interpret documentation as follows:

AGENTS.md
→ durable repository-wide development rules

plan.md
→ CURRENT authorized work

architecture.md
→ technical architecture and architectural decisions

PROJECT_CONTEXT.md
→ broader product vision and long-term context

CLAUDE.md
→ Claude-specific instructions / compatibility

Laravel Boost skills/guidelines
→ framework-specific implementation guidance

README.md
→ human setup and usage documentation

If PROJECT_CONTEXT.md describes a future feature but plan.md does not authorize it:

DO NOT IMPLEMENT IT.

---

# 27. Current Project Status

The application foundation has been created using:

Laravel 13
PHP 8.4
Livewire 4
Blade
Flux UI
Tailwind CSS
Laravel Fortify
Laravel Boost
Pest
SQLite during development

Phase 1 establishes:

- authentication
- roles
- authorization
- MediaFile domain
- Transcription domain
- TranscriptionSegment domain
- ProcessingJob domain
- dashboard
- transcription pages
- media pages
- prototype processing UI
- settings
- realistic demo data

Phase 1 does NOT perform real transcription.

---

# 28. Planned Development Roadmap

## Phase 1
Application Foundation + Clickable Prototype

## Phase 2
Real File Upload + Media Library

Expected focus:

- real binary uploads
- private storage
- validation
- size limits
- ownership
- media lifecycle

## Phase 3
Media Processing

Expected focus:

- FFprobe
- metadata extraction
- FFmpeg
- audio extraction
- audio normalization
- temporary media handling

## Phase 4
Independent Transcription Worker

Expected focus:

- Python
- faster-whisper
- CPU execution
- model benchmarking
- timestamped results
- worker CLI/service contract

## Phase 5
Laravel ↔ Worker Integration

Expected focus:

- queue
- asynchronous transcription
- progress
- retries
- failure handling
- result persistence

At this stage the core product should finally support:

Upload
→ Process
→ Transcribe
→ View Result

## Phase 6
Transcript Experience

Expected focus:

- media player
- timestamp seeking
- transcript synchronization
- search
- copy
- TXT
- SRT
- VTT

## Future Phase — Translation

Expected focus:

- translation architecture
- Bahasa Melayu Malaysia output
- multilingual handling
- original vs translated transcript
- translation persistence
- translation UX

## Future Phase — AI Intelligence

Possible:

- summary
- key points
- action items
- transcript Q&A

## Future Phase — Live Transcription

Expected focus:

- microphone capture
- streaming/chunking
- partial results
- final results
- live transcript UI

## Future Phase — Live Translation

Expected focus:

- realtime translation
- latency
- sentence stabilization
- multilingual context

## Production Hardening

Expected focus:

- PostgreSQL
- Redis
- production workers
- object storage
- monitoring
- retries
- cleanup
- security
- backup
- worker scaling
- GPU infrastructure

---

# 29. Product Success Criteria

The product should eventually succeed at this simple user journey:

User records or receives audio/video.

        ↓

User uploads it to RTFTT Transcribe AI.

        ↓

The application safely stores and processes it.

        ↓

The transcription worker converts speech into timestamped text.

        ↓

The user can comfortably read and navigate the transcript.

        ↓

If required, the user translates it into natural Bahasa Melayu or another language.

        ↓

The user can search, copy, review and export the result.

For live use:

Speaker talks.

        ↓

RTFTT receives the audio.

        ↓

Transcript appears with low enough latency to be useful.

        ↓

Optional translated text follows.

The architecture should continuously move toward these outcomes without overengineering before the relevant phase.

---

# 30. Core Principle for AI Coding Agents

When making an engineering decision, ask:

"Does this make the current authorized phase simpler and reliable while preserving a reasonable path toward the real transcription product?"

Prefer that over:

"Can we implement every future feature now?"

RTFTT Transcribe AI should grow from a reliable core.

The most important sequence is:

Reliable Media Handling
        ↓
Reliable Transcription
        ↓
Excellent Transcript UX
        ↓
Reliable Translation
        ↓
Advanced AI
        ↓
Live Experience
        ↓
Scale
