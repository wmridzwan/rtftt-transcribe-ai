"""Self-hosted translation (ADR-022, D5-04).

Provider-neutral boundary default. Uses a configurable, lazily-loaded
sequence-to-sequence model (default: a multilingual NLLB checkpoint) exposed
through the `transformers` runtime. The runtime is an optional dependency: if it
is not installed the endpoint returns a CONFIGURATION_ERROR envelope rather than
crashing, so the application boundary is testable without the heavy model stack.
"""

from __future__ import annotations

import os

CONTRACT_VERSION = "1.0"

PROVIDER_NAME = "self-hosted"

DEFAULT_MODEL = "facebook/nllb-200-distilled-600M"

# BCP 47 (rtftt vocabulary) -> NLLB-200 / FLORES-200 language codes.
# Standard Malay is `zsm_Latn` in FLORES-200 (`msa_Latn` is not a valid code and
# resolves to the unknown token, producing garbage).
NLLB_CODES = {
    "ms": "zsm_Latn",
    "en": "eng_Latn",
    "zh": "zho_Hans",
    "ta": "tam_Taml",
}

DEFAULT_SOURCE_CODE = "eng_Latn"

_model = None
_tokenizer = None


class TranslationError(Exception):
    """Structured translation failure carried as a worker error envelope."""

    def __init__(self, code: str, safe_message: str = "Translation failed."):
        super().__init__(safe_message)
        self.code = code
        self.safe_message = safe_message


def model_name() -> str:
    return os.environ.get("RTFTT_TRANSLATION_MODEL", DEFAULT_MODEL)


def get_model():
    """Lazily load and cache the translation model and tokenizer."""
    global _model, _tokenizer

    if _model is not None and _tokenizer is not None:
        return _model, _tokenizer

    try:
        from transformers import AutoModelForSeq2SeqLM, AutoTokenizer
    except Exception as exc:  # pragma: no cover - depends on optional runtime
        raise TranslationError(
            "CONFIGURATION_ERROR",
            "Translation model runtime is not available.",
        ) from exc

    try:
        name = model_name()
        _tokenizer = AutoTokenizer.from_pretrained(name)
        _model = AutoModelForSeq2SeqLM.from_pretrained(name)
    except Exception as exc:  # pragma: no cover - depends on optional runtime
        raise TranslationError(
            "PROVIDER_UNAVAILABLE",
            "Translation model could not be loaded.",
        ) from exc

    return _model, _tokenizer


def _translate_text(model, tokenizer, text: str, target_language: str, source_language: str) -> str:
    code = NLLB_CODES[target_language]

    # NLLB requires an explicit source language; otherwise it defaults to
    # English and mistranslates non-English source segments.
    tokenizer.src_lang = NLLB_CODES.get(source_language, DEFAULT_SOURCE_CODE)

    inputs = tokenizer(text, return_tensors="pt", truncation=True, max_length=512)
    generated = model.generate(
        **inputs,
        forced_bos_token_id=tokenizer.convert_tokens_to_ids(code),
        max_length=512,
    )
    return tokenizer.batch_decode(generated, skip_special_tokens=True)[0]


def translate_segments(segments: list[dict], target_language: str) -> dict:
    """Translate segment-aligned source text, preserving index/timestamps.

    Already-target-language segments are passed through without a model call.
    """
    if target_language not in NLLB_CODES:
        raise TranslationError("INVALID_REQUEST", "Unsupported target language.")

    model = tokenizer = None
    translated_segments = []
    full_text_parts = []

    for segment in segments:
        text = str(segment.get("text", ""))
        source_language = str(segment.get("source_language", "und"))

        if source_language == target_language:
            translated = text
        else:
            if model is None:
                model, tokenizer = get_model()
            translated = _translate_text(model, tokenizer, text, target_language, source_language)

        translated_segments.append({
            "segment_index": int(segment["segment_index"]),
            "start_seconds": float(segment["start_seconds"]),
            "end_seconds": float(segment["end_seconds"]),
            "text": translated,
            "source_language": source_language,
        })
        full_text_parts.append(translated)

    return {
        "contract_version": CONTRACT_VERSION,
        "target_language": target_language,
        "provider": PROVIDER_NAME,
        "model": model_name(),
        "text": " ".join(full_text_parts).strip(),
        "segments": translated_segments,
    }