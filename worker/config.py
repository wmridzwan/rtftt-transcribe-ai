"""Worker configuration."""

import os
from pathlib import Path


WORKER_TOKEN = os.environ.get("RTFTT_WORKER_TOKEN", "")
SHARED_MEDIA_ROOT = Path(os.environ.get("RTFTT_SHARED_MEDIA_ROOT", "/media"))
PREPARED_AUDIO_DIR = Path(os.environ.get("RTFTT_PREPARED_AUDIO_DIR", "/tmp/rtftt-prepared"))
MODEL_NAME = os.environ.get("RTFTT_WHISPER_MODEL", "turbo")
DEVICE = os.environ.get("RTFTT_WHISPER_DEVICE", "auto")
COMPUTE_TYPE = os.environ.get("RTFTT_WHISPER_COMPUTE_TYPE", "float16")
CONTRACT_VERSION = "1.0"
MAX_WORKER_TIMEOUT = int(os.environ.get("RTFTT_WORKER_TIMEOUT", "300"))
PREPARED_AUDIO_RETENTION = os.environ.get("RTFTT_PREPARED_AUDIO_RETENTION", "ephemeral")
