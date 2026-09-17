"""Tests for transcription (worker/transcription.py).

Tests match the real faster-whisper API shape. Segment.language does NOT
exist in faster-whisper; per-segment language is derived via
WhisperModel.detect_language() on each segment's audio span.
"""

from pathlib import Path
from unittest.mock import patch, MagicMock

import numpy as np
import pytest


class TestTranscribeAudio:
    """Test transcription with mocked faster-whisper."""

    def _make_mock_segment(self, index, start, end, text):
        """Create a mock segment object matching real faster-whisper API."""
        seg = MagicMock()
        seg.start = start
        seg.end = end
        seg.text = text
        # NOTE: faster-whisper Segment does NOT have a language attribute.
        # Do NOT set seg.language — this is the fiction H5 found.
        return seg

    def _make_mock_info(self, language="en", duration=10.0):
        """Create a mock transcription info object."""
        info = MagicMock()
        info.language = language
        info.duration = duration
        return info

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_requested_language_forwarded(self, mock_get_model, mock_detect_lang):
        """Explicit requested_language is passed to model.transcribe()."""
        mock_detect_lang.return_value = "en"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 5.0, "Hello")],
            self._make_mock_info("en", 5.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"), requested_language="ms")

        mock_model.transcribe.assert_called_once()
        call_kwargs = mock_model.transcribe.call_args
        assert call_kwargs[1]["language"] == "ms"

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_auto_detect_when_null(self, mock_get_model, mock_detect_lang):
        """None requested_language produces auto-detect (language=None)."""
        mock_detect_lang.return_value = "en"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 5.0, "Hello")],
            self._make_mock_info("en", 5.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"), requested_language=None)

        call_kwargs = mock_model.transcribe.call_args
        assert call_kwargs[1]["language"] is None

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_normal_transcript(self, mock_get_model, mock_detect_lang):
        """Normal transcript returns expected structure."""
        mock_detect_lang.return_value = "en"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [
                self._make_mock_segment(0, 0.0, 3.0, "Hello."),
                self._make_mock_segment(1, 3.0, 6.0, "World."),
            ],
            self._make_mock_info("en", 6.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["speech_detected"] is True
        assert result["text"] == "Hello. World."
        assert result["language"] == "en"
        assert len(result["segments"]) == 2

    @patch("worker.transcription.get_model")
    def test_no_speech_result(self, mock_get_model):
        """No-speech result returns expected structure."""
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [],
            self._make_mock_info(None, 10.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["speech_detected"] is False
        assert result["text"] == ""
        assert result["language"] == "und"
        assert result["segments"] == []

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_segment_language_not_copied_from_transcript(self, mock_get_model, mock_detect_lang):
        """Segment languages are derived per-segment, not copied from transcript.

        The mock_detect_lang simulates different detected languages for
        different segments. This tests that the worker calls
        _detect_segment_language for each segment independently."""
        # Simulate different language detection results per segment
        mock_detect_lang.side_effect = ["en", "ms", "zh"]
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [
                self._make_mock_segment(0, 0.0, 3.0, "Hello"),
                self._make_mock_segment(1, 3.0, 6.0, "Selamat"),
                self._make_mock_segment(2, 6.0, 9.0, "你好"),
            ],
            self._make_mock_info("en", 9.0),  # Transcript says English
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        # Transcript-level language is English (dominant)
        assert result["language"] == "en"
        # But segments have their own independently detected languages
        assert result["segments"][0]["language"] == "en"
        assert result["segments"][1]["language"] == "ms"
        assert result["segments"][2]["language"] == "zh"
        # Verify _detect_segment_language was called for each segment
        assert mock_detect_lang.call_count == 3

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_und_fallback_for_unknown_language(self, mock_get_model, mock_detect_lang):
        """Segment with uncertain detection falls back to und."""
        mock_detect_lang.return_value = "und"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 3.0, "Hello")],
            self._make_mock_info("en", 3.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["segments"][0]["language"] == "und"

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_requested_language_does_not_force_segment_language(self, mock_get_model, mock_detect_lang):
        """requested_language is a transcription hint, not a segment-language override.

        Even when requested_language='ms', segment-level language detection
        should still reflect the actual segment audio."""
        # Segment audio is English, detected as "en" despite request for "ms"
        mock_detect_lang.return_value = "en"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 3.0, "Hello world")],
            self._make_mock_info("en", 3.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"), requested_language="ms")

        # The hint was forwarded to transcribe
        call_kwargs = mock_model.transcribe.call_args
        assert call_kwargs[1]["language"] == "ms"
        # But segment language reflects actual detection, not the hint
        assert result["segments"][0]["language"] == "en"

    @patch("worker.transcription._detect_segment_language")
    @patch("worker.transcription.get_model")
    def test_contract_version_in_response(self, mock_get_model, mock_detect_lang):
        """Response includes contract version."""
        mock_detect_lang.return_value = "en"
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 3.0, "Hello")],
            self._make_mock_info("en", 3.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["contract_version"] == "1.0"


class TestDetectSegmentLanguage:
    """Test per-segment language detection (worker/transcription.py:_detect_segment_language)."""

    @patch("worker.transcription.get_model")
    def test_confident_detection(self, mock_get_model):
        """High-confidence detection returns detected language."""
        mock_model = MagicMock()
        mock_model.detect_language.return_value = ("en", 0.95, [("en", 0.95), ("ms", 0.05)])
        mock_get_model.return_value = mock_model

        from worker.transcription import _detect_segment_language

        # Mock _read_audio_segment to return fake audio
        with patch("worker.transcription._read_audio_segment") as mock_read:
            mock_read.return_value = np.zeros(16000, dtype=np.float32)  # 1 second silence
            result = _detect_segment_language(mock_model, Path("/fake.wav"), 0.0, 1.0)

        assert result == "en"

    @patch("worker.transcription.get_model")
    def test_low_confidence_returns_und(self, mock_get_model):
        """Low-confidence detection returns und."""
        mock_model = MagicMock()
        mock_model.detect_language.return_value = ("en", 0.3, [("en", 0.3), ("ms", 0.25)])
        mock_get_model.return_value = mock_model

        from worker.transcription import _detect_segment_language

        with patch("worker.transcription._read_audio_segment") as mock_read:
            mock_read.return_value = np.zeros(16000, dtype=np.float32)
            result = _detect_segment_language(mock_model, Path("/fake.wav"), 0.0, 1.0)

        assert result == "und"

    @patch("worker.transcription.get_model")
    def test_detection_exception_returns_und(self, mock_get_model):
        """Exception during detection returns und."""
        mock_model = MagicMock()
        mock_model.detect_language.side_effect = RuntimeError("detection failed")
        mock_get_model.return_value = mock_model

        from worker.transcription import _detect_segment_language

        with patch("worker.transcription._read_audio_segment") as mock_read:
            mock_read.return_value = np.zeros(16000, dtype=np.float32)
            result = _detect_segment_language(mock_model, Path("/fake.wav"), 0.0, 1.0)

        assert result == "und"

    def test_empty_segment_returns_und(self):
        """Empty audio segment returns und."""
        from worker.transcription import _detect_segment_language

        mock_model = MagicMock()
        with patch("worker.transcription._read_audio_segment") as mock_read:
            mock_read.return_value = np.array([], dtype=np.float32)
            result = _detect_segment_language(mock_model, Path("/fake.wav"), 0.0, 0.0)

        assert result == "und"
        mock_model.detect_language.assert_not_called()


class TestGetModel:
    """Test device/compute_type resolution (worker/transcription.py:get_model)."""

    def setup_method(self):
        import worker.transcription as transcription_module
        transcription_module._model = None

    def teardown_method(self):
        import worker.transcription as transcription_module
        transcription_module._model = None

    @patch("worker.transcription.WhisperModel")
    @patch("worker.transcription.ctranslate2")
    def test_auto_resolves_to_cpu_without_torch(self, mock_ctranslate2, mock_whisper_model, monkeypatch):
        """device='auto' with no CUDA devices resolves to cpu, with no
        dependency on torch."""
        mock_ctranslate2.get_cuda_device_count.return_value = 0
        monkeypatch.setattr("worker.config.DEVICE", "auto")
        monkeypatch.setattr("worker.config.COMPUTE_TYPE", "float16")

        from worker.transcription import get_model
        get_model()

        mock_whisper_model.assert_called_once()
        _, kwargs = mock_whisper_model.call_args
        assert kwargs["device"] == "cpu"
        assert kwargs["compute_type"] == "int8"

    @patch("worker.transcription.WhisperModel")
    @patch("worker.transcription.ctranslate2")
    def test_explicit_cpu_downgrades_float16_to_int8(self, mock_ctranslate2, mock_whisper_model, monkeypatch):
        """Explicit device='cpu' with compute_type='float16' is downgraded to 'int8'."""
        monkeypatch.setattr("worker.config.DEVICE", "cpu")
        monkeypatch.setattr("worker.config.COMPUTE_TYPE", "float16")

        from worker.transcription import get_model
        get_model()

        mock_ctranslate2.get_cuda_device_count.assert_not_called()
        _, kwargs = mock_whisper_model.call_args
        assert kwargs["device"] == "cpu"
        assert kwargs["compute_type"] == "int8"

    @patch("worker.transcription.WhisperModel")
    @patch("worker.transcription.ctranslate2")
    def test_model_is_cached_after_first_call(self, mock_ctranslate2, mock_whisper_model, monkeypatch):
        """get_model() only constructs WhisperModel once (lazy singleton)."""
        mock_ctranslate2.get_cuda_device_count.return_value = 0
        monkeypatch.setattr("worker.config.DEVICE", "auto")
        monkeypatch.setattr("worker.config.COMPUTE_TYPE", "int8")

        from worker.transcription import get_model
        first = get_model()
        second = get_model()

        assert first is second
        mock_whisper_model.assert_called_once()
