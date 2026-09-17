"""faster-whisper transcription with per-segment language identification."""

import wave
from pathlib import Path

import ctranslate2
import numpy as np
from faster_whisper import WhisperModel

from . import config

_model = None

# Language detection confidence threshold.
# Below this probability, segment language falls back to "und".
LANGUAGE_CONFIDENCE_THRESHOLD = 0.5


def get_model() -> WhisperModel:
    """Get or initialize the faster-whisper model (lazy loading)."""
    global _model
    if _model is None:
        device = config.DEVICE
        if device == "auto":
            device = "cuda" if ctranslate2.get_cuda_device_count() > 0 else "cpu"

        compute_type = config.COMPUTE_TYPE
        if device == "cpu" and compute_type == "float16":
            compute_type = "int8"

        _model = WhisperModel(
            config.MODEL_NAME,
            device=device,
            compute_type=compute_type,
        )
    return _model


def _read_audio_segment(audio_path: Path, start_seconds: float, end_seconds: float) -> np.ndarray:
    """Extract a mono 16kHz float32 audio segment from a WAV file."""
    with wave.open(str(audio_path), "rb") as wf:
        sample_rate = wf.getframerate()
        n_channels = wf.getnchannels()
        sample_width = wf.getsampwidth()

        start_frame = int(start_seconds * sample_rate)
        end_frame = int(end_seconds * sample_rate)
        n_frames = end_frame - start_frame

        if n_frames <= 0:
            return np.array([], dtype=np.float32)

        # Seek and read
        wf.setpos(start_frame)
        raw_data = wf.readframes(n_frames)

    # Convert to numpy array
    if sample_width == 2:
        dtype = np.int16
    elif sample_width == 4:
        dtype = np.int32
    else:
        dtype = np.int16

    audio = np.frombuffer(raw_data, dtype=dtype)

    # Mix down to mono if stereo
    if n_channels > 1:
        audio = audio.reshape(-1, n_channels).mean(axis=1)

    # Normalize to float32 [-1, 1]
    max_val = float(2 ** (8 * sample_width - 1))
    audio = audio.astype(np.float32) / max_val

    return audio


def _detect_segment_language(
    model: WhisperModel,
    audio_path: Path,
    start_seconds: float,
    end_seconds: float,
    requested_language: str | None = None,
) -> str:
    """
    Detect language for a single audio segment using WhisperModel.detect_language().

    Returns BCP-47 language code, or "und" if:
    - confidence is below threshold
    - no speech detected
    - detection fails
    """
    segment_audio = _read_audio_segment(audio_path, start_seconds, end_seconds)

    if len(segment_audio) == 0:
        return "und"

    # Ensure minimum length for reliable detection (at least 0.5 seconds)
    min_samples = int(0.5 * config.SAMPLE_RATE)  # 8000 samples at 16kHz
    if len(segment_audio) < min_samples:
        # Pad with silence for detection
        padding = np.zeros(min_samples - len(segment_audio), dtype=np.float32)
        segment_audio = np.concatenate([segment_audio, padding])

    try:
        language, probability, _ = model.detect_language(
            audio=segment_audio,
            language_detection_threshold=LANGUAGE_CONFIDENCE_THRESHOLD,
        )

        if probability >= LANGUAGE_CONFIDENCE_THRESHOLD:
            return language
        else:
            return "und"

    except Exception:
        return "und"


def transcribe_audio(audio_path: Path, requested_language: str | None = None) -> dict:
    """
    Transcribe prepared audio with per-segment language identification.

    Args:
        audio_path: Path to prepared WAV audio file (16kHz mono PCM 16-bit).
        requested_language: Optional BCP 47 language hint (e.g., 'ms', 'en').
            None means auto-detect. The hint is passed to faster-whisper
            but does not guarantee single-language output (code-switching
            support remains required). Per-segment language identification
            still reflects the actual segment audio.

    Returns normalized response dict matching WorkerResponse contract.
    Segment language is derived per-segment via WhisperModel.detect_language(),
    not copied from transcript-level info.
    """
    model = get_model()

    # Pass language hint to faster-whisper; None = auto-detect
    language_param = requested_language if requested_language else None

    segments_iter, info = model.transcribe(
        str(audio_path),
        language=language_param,
        task="transcribe",
        vad_filter=True,
    )

    segments = []
    full_text_parts = []

    for i, seg in enumerate(segments_iter):
        # Genuine per-segment language detection using detect_language()
        seg_lang = _detect_segment_language(
            model,
            audio_path,
            seg.start,
            seg.end,
            requested_language,
        )

        segments.append({
            "segment_index": i,
            "start_seconds": round(seg.start, 3),
            "end_seconds": round(seg.end, 3),
            "text": seg.text.strip(),
            "language": seg_lang,
        })
        full_text_parts.append(seg.text.strip())

    full_text = " ".join(full_text_parts).strip()
    detected_language = info.language or "und"
    speech_detected = bool(full_text or segments)

    # No-speech: speech_detected=false, text="", language=und, segments=[]
    if not speech_detected:
        return {
            "contract_version": config.CONTRACT_VERSION,
            "text": "",
            "language": "und",
            "duration_seconds": round(info.duration if hasattr(info, "duration") else 0.0, 3),
            "speech_detected": False,
            "segments": [],
        }

    return {
        "contract_version": config.CONTRACT_VERSION,
        "text": full_text,
        "language": detected_language,
        "duration_seconds": round(info.duration if hasattr(info, "duration") else 0.0, 3),
        "speech_detected": True,
        "segments": segments,
    }
