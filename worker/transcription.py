"""faster-whisper transcription with multilingual support."""

from pathlib import Path

from faster_whisper import WhisperModel

from . import config

_model = None


def get_model() -> WhisperModel:
    """Get or initialize the faster-whisper model (lazy loading)."""
    global _model
    if _model is None:
        device = config.DEVICE
        if device == "auto":
            import torch
            device = "cuda" if torch.cuda.is_available() else "cpu"

        compute_type = config.COMPUTE_TYPE
        if device == "cpu" and compute_type == "float16":
            compute_type = "int8"

        _model = WhisperModel(
            config.MODEL_NAME,
            device=device,
            compute_type=compute_type,
        )
    return _model


def transcribe_audio(audio_path: Path, requested_language: str | None = None) -> dict:
    """
    Transcribe prepared audio with faster-whisper.

    Args:
        audio_path: Path to prepared WAV audio file.
        requested_language: Optional BCP 47 language hint (e.g., 'ms', 'en').
            None means auto-detect. The hint is passed to faster-whisper
            but does not guarantee single-language output (code-switching
            support remains required).

    Returns normalized response dict matching WorkerResponse contract.
    Segment language is derived per-segment, not copied from transcript.
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
        # Use segment-level language if available, else fall back to info.language
        seg_lang = getattr(seg, "language", None) or info.language or "und"

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
