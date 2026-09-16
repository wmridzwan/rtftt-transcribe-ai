"""FFmpeg audio preparation: 16 kHz, mono, PCM 16-bit."""

import subprocess
import tempfile
from pathlib import Path

from . import config


class FfmpegError(Exception):
    """Raised when FFmpeg processing fails."""

    pass


def prepare_audio(input_path: Path) -> Path:
    """
    Prepare audio for faster-whisper inference.

    Output: 16 kHz, mono, PCM 16-bit WAV.
    Uses argument-array execution (no shell interpolation).
    """
    config.PREPARED_AUDIO_DIR.mkdir(parents=True, exist_ok=True)

    output_path = Path(
        tempfile.mktemp(
            dir=str(config.PREPARED_AUDIO_DIR),
            suffix=".wav",
        )
    )

    cmd = [
        "ffmpeg",
        "-y",
        "-i",
        str(input_path),
        "-ar",
        "16000",
        "-ac",
        "1",
        "-sample_fmt",
        "s16",
        "-f",
        "wav",
        str(output_path),
    ]

    try:
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=config.MAX_WORKER_TIMEOUT,
        )

        if result.returncode != 0:
            # Cleanup on failure
            if output_path.exists():
                output_path.unlink()

            stderr_truncated = result.stderr[:1000] if result.stderr else ""
            raise FfmpegError(
                f"FFmpeg exited with code {result.returncode}: {stderr_truncated}"
            )

        if not output_path.exists() or output_path.stat().st_size == 0:
            raise FfmpegError("FFmpeg produced no output")

        return output_path

    except subprocess.TimeoutExpired:
        if output_path.exists():
            output_path.unlink()
        raise FfmpegError("FFmpeg timed out")
    except FileNotFoundError:
        raise FfmpegError("FFmpeg not found")
