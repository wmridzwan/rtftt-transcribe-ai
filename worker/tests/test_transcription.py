"""Tests for transcription (worker/transcription.py)."""

from pathlib import Path
from unittest.mock import patch, MagicMock, PropertyMock

import pytest


class TestTranscribeAudio:
    """Test transcription with mocked faster-whisper."""

    def _make_mock_segment(self, index, start, end, text, language="en"):
        """Create a mock segment object."""
        seg = MagicMock()
        seg.start = start
        seg.end = end
        seg.text = text
        seg.language = language
        return seg

    def _make_mock_info(self, language="en", duration=10.0):
        """Create a mock transcription info object."""
        info = MagicMock()
        info.language = language
        info.duration = duration
        return info

    @patch("worker.transcription.get_model")
    def test_requested_language_forwarded(self, mock_get_model):
        """Explicit requested_language is passed to model.transcribe()."""
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

    @patch("worker.transcription.get_model")
    def test_auto_detect_when_null(self, mock_get_model):
        """None requested_language produces auto-detect (language=None)."""
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

    @patch("worker.transcription.get_model")
    def test_normal_transcript(self, mock_get_model):
        """Normal transcript returns expected structure."""
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

    @patch("worker.transcription.get_model")
    def test_segment_language_not_copied_from_transcript(self, mock_get_model):
        """Segment languages are per-segment, not copied from transcript."""
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [
                self._make_mock_segment(0, 0.0, 3.0, "Hello", "en"),
                self._make_mock_segment(1, 3.0, 6.0, "Selamat", "ms"),
                self._make_mock_segment(2, 6.0, 9.0, "你好", "zh"),
            ],
            self._make_mock_info("en", 9.0),  # Transcript says English
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        # Transcript-level language is English (dominant)
        assert result["language"] == "en"
        # But segments have their own languages
        assert result["segments"][0]["language"] == "en"
        assert result["segments"][1]["language"] == "ms"
        assert result["segments"][2]["language"] == "zh"

    @patch("worker.transcription.get_model")
    def test_und_fallback_for_unknown_language(self, mock_get_model):
        """Segment with unknown language falls back to und."""
        mock_model = MagicMock()
        seg = self._make_mock_segment(0, 0.0, 3.0, "Hello")
        seg.language = None  # Simulate unknown
        mock_model.transcribe.return_value = (
            [seg],
            self._make_mock_info(None, 3.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["segments"][0]["language"] == "und"

    @patch("worker.transcription.get_model")
    def test_contract_version_in_response(self, mock_get_model):
        """Response includes contract version."""
        mock_model = MagicMock()
        mock_model.transcribe.return_value = (
            [self._make_mock_segment(0, 0.0, 3.0, "Hello")],
            self._make_mock_info("en", 3.0),
        )
        mock_get_model.return_value = mock_model

        from worker.transcription import transcribe_audio
        result = transcribe_audio(Path("/fake/audio.wav"))

        assert result["contract_version"] == "1.0"
