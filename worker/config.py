"""Worker configuration."""

import os
from pathlib import Path


WORKER_TOKEN = os.environ.get("RTFTT_TRANSCRIPTION_WORKER_TOKEN", "")
SHARED_MEDIA_ROOT = Path(os.environ.get("RTFTT_SHARED_MEDIA_ROOT", "/media"))
PREPARED_AUDIO_DIR = Path(os.environ.get("RTFTT_PREPARED_AUDIO_DIR", "/tmp/rtftt-prepared"))
# Canonical Phase 3 default model (HPO decision 2026-09-18).
# large-v3 selected over turbo after expanded Tamil benchmark showed
# repeated cross-script corruption on real Tamil samples with turbo.
# turbo remains available as a non-default experimental profile via env.
MODEL_NAME = os.environ.get("RTFTT_WHISPER_MODEL", "large-v3")
DEVICE = os.environ.get("RTFTT_WHISPER_DEVICE", "auto")
COMPUTE_TYPE = os.environ.get("RTFTT_WHISPER_COMPUTE_TYPE", "float16")
CONTRACT_VERSION = "1.0"
SAMPLE_RATE = 16000  # faster-whisper expects 16kHz mono audio
MAX_WORKER_TIMEOUT = int(os.environ.get("RTFTT_WORKER_TIMEOUT", "300"))
PREPARED_AUDIO_RETENTION = os.environ.get("RTFTT_PREPARED_AUDIO_RETENTION", "ephemeral")
