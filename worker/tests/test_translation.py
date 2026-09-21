"""Tests for the worker translation endpoint and self-hosted translation module."""

from unittest.mock import patch

import pytest
from fastapi.testclient import TestClient

from worker import translation
from worker.auth import verify_token
from worker.main import app


@pytest.fixture
def client():
    app.dependency_overrides[verify_token] = lambda: "test-token"
    yield TestClient(app)
    app.dependency_overrides.clear()


def _body(target_language="ms", source_language="en"):
    return {
        "request_id": "req-1",
        "transcription_id": 1,
        "translation_id": 2,
        "target_language": target_language,
        "segments": [
            {
                "segment_index": 0,
                "start_seconds": 0.0,
                "end_seconds": 5.0,
                "text": "Hello",
                "source_language": source_language,
            }
        ],
    }


def _result():
    return {
        "contract_version": "1.0",
        "target_language": "ms",
        "provider": "self-hosted",
        "model": "self-hosted-default",
        "text": "Hai",
        "segments": [
            {
                "segment_index": 0,
                "start_seconds": 0.0,
                "end_seconds": 5.0,
                "text": "Hai",
                "source_language": "en",
            }
        ],
    }


def test_translate_success(client):
    with patch("worker.main.translate_segments", return_value=_result()):
        response = client.post("/translate", json=_body())

    assert response.status_code == 200
    body = response.json()
    assert body["target_language"] == "ms"
    assert body["segments"][0]["text"] == "Hai"
    assert body["segments"][0]["segment_index"] == 0


def test_translate_rejects_unsupported_target(client):
    response = client.post("/translate", json=_body(target_language="fr"))

    assert response.status_code == 422
    assert response.json()["error_code"] == "INVALID_REQUEST"


def test_translate_maps_translation_error_to_envelope(client):
    error = translation.TranslationError("PROVIDER_UNAVAILABLE", "Model unavailable.")
    with patch("worker.main.translate_segments", side_effect=error):
        response = client.post("/translate", json=_body())

    assert response.status_code == 503
    body = response.json()
    assert body["error_code"] == "PROVIDER_UNAVAILABLE"
    assert body["retryable"] is True
    assert body["safe_message"] == "Model unavailable."


def test_passthrough_when_source_matches_target():
    result = translation.translate_segments(
        [
            {
                "segment_index": 0,
                "start_seconds": 0.0,
                "end_seconds": 5.0,
                "text": "Hai",
                "source_language": "ms",
            }
        ],
        "ms",
    )

    assert result["segments"][0]["text"] == "Hai"
    assert result["target_language"] == "ms"


def test_nllb_code_mapping_uses_flores_standard():
    # Standard Malay is zsm_Latn in FLORES-200; msa_Latn resolves to the unk id.
    assert translation.NLLB_CODES["ms"] == "zsm_Latn"
    assert translation.NLLB_CODES["en"] == "eng_Latn"
    assert translation.NLLB_CODES["zh"] == "zho_Hans"
    assert translation.NLLB_CODES["ta"] == "tam_Taml"

def test_translation_model_dependencies_are_exactly_pinned():
    # P5-004B: the real-model gate (P5-008) must verify the set that ships, so the
    # approved Phase 5 model dependencies may not drift to open-ended `>=` ranges.
    from pathlib import Path

    requirements = (Path(__file__).resolve().parents[1] / "requirements.txt").read_text().splitlines()
    declared = {}
    for line in requirements:
        line = line.strip()
        if line and not line.startswith("#"):
            name = line.replace("==", " ").replace(">=", " ").replace("<", " ").split()[0]
            declared[name] = line

    for name in ("transformers", "torch", "sentencepiece"):
        assert name in declared, f"{name} must be declared"
        assert "==" in declared[name] and ">=" not in declared[name], declared[name]
