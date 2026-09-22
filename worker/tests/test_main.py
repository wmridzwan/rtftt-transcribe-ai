"""Tests for the worker error envelope semantics (worker/main.py).

P3-007 / ADR-018: Laravel's TranscriptionFailure taxonomy is the authoritative
domain retryability source. The worker's advisory `retryable` flag is aligned so
no cross-layer retryability contradiction remains.
"""

from pathlib import Path
from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient

from worker.auth import verify_token
from worker.ffmpeg import FfmpegError
from worker.main import app
from worker.media import MediaAccessError


@pytest.fixture
def client():
    app.dependency_overrides[verify_token] = lambda: "test-token"
    yield TestClient(app)
    app.dependency_overrides.clear()


def _request_body():
    return {
        "request_id": "req-1",
        "transcription_id": 1,
        "attempt_id": 2,
        "media_reference": {
            "storage_key": "media/x.mp3",
            "mime_type": "audio/mpeg",
            "file_size_bytes": 1024,
        },
    }


class TestErrorEnvelope:
    def test_ffmpeg_failure_is_not_retryable(self, client):
        with patch("worker.main.resolve_media_path", return_value=Path("/fake/in.mp3")), \
                patch("worker.main.prepare_audio", side_effect=FfmpegError("boom")):
            response = client.post("/transcribe", json=_request_body())

        assert response.status_code == 500
        body = response.json()
        assert body["error_code"] == "FFMPEG_FAILED"
        assert body["retryable"] is False
        assert body["request_id"] == "req-1"
        assert body["safe_message"] == "Audio processing failed."

    def test_media_access_failure_is_not_retryable(self, client):
        with patch("worker.main.resolve_media_path", side_effect=MediaAccessError("nope")):
            response = client.post("/transcribe", json=_request_body())

        assert response.status_code == 422
        body = response.json()
        assert body["error_code"] == "MEDIA_REJECTED"
        assert body["retryable"] is False

    def test_unexpected_processing_failure_is_not_retryable(self, client):
        with patch("worker.main.resolve_media_path", side_effect=RuntimeError("boom")):
            response = client.post("/transcribe", json=_request_body())

        assert response.status_code == 500
        body = response.json()
        assert body["error_code"] == "PROCESSING_FAILED"
        assert body["retryable"] is False
