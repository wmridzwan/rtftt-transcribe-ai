"""RTFTT Transcription Worker — FastAPI application."""

import logging
import os
import time
from pathlib import Path

from fastapi import Depends, FastAPI, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel

from .auth import verify_token
from .config import PREPARED_AUDIO_RETENTION, MAX_WORKER_TIMEOUT
from .ffmpeg import FfmpegError, prepare_audio
from .media import MediaAccessError, resolve_media_path
from .transcription import transcribe_audio

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("rtftt-worker")

app = FastAPI(title="RTFTT Transcription Worker", version="1.0")


class MediaReference(BaseModel):
    storage_key: str
    mime_type: str
    file_size_bytes: int
    duration_seconds: float | None = None


class TranscriptionRequest(BaseModel):
    request_id: str
    transcription_id: int
    attempt_id: int
    media_reference: MediaReference
    requested_language: str | None = None
    contract_version: str = "1.0"


def error_response(error_code: str, retryable: bool, safe_message: str, request_id: str) -> JSONResponse:
    """Create a structured error response."""
    return JSONResponse(
        status_code=422 if error_code in ("MEDIA_MISSING", "MEDIA_REJECTED") else 500,
        content={
            "error_code": error_code,
            "retryable": retryable,
            "safe_message": safe_message,
            "request_id": request_id,
        },
    )


@app.post("/transcribe")
async def transcribe(
    request: TranscriptionRequest,
    token: str = Depends(verify_token),
) -> JSONResponse:
    """Transcribe media via internal authenticated endpoint."""
    start_time = time.time()
    prepared_path = None

    try:
        # Resolve media path (rejects traversal, absolute paths)
        media_path = resolve_media_path(request.media_reference.storage_key)

        # Prepare audio (FFmpeg)
        prepared_path = prepare_audio(media_path)

        # Transcribe (faster-whisper)
        result = transcribe_audio(prepared_path)

        processing_time = time.time() - start_time
        logger.info(
            "transcription_complete",
            extra={
                "request_id": request.request_id,
                "transcription_id": request.transcription_id,
                "attempt_id": request.attempt_id,
                "processing_seconds": round(processing_time, 3),
                "model": os.environ.get("RTFTT_WHISPER_MODEL", "turbo"),
                "speech_detected": result["speech_detected"],
                "segment_count": len(result["segments"]),
            },
        )

        return JSONResponse(content=result)

    except MediaAccessError as e:
        logger.warning("media_access_error", extra={"request_id": request.request_id, "error": str(e)})
        return error_response("MEDIA_REJECTED", False, str(e), request.request_id)

    except FfmpegError as e:
        logger.warning("ffmpeg_error", extra={"request_id": request.request_id, "error": str(e)})
        return error_response("FFMPEG_FAILED", True, "Audio processing failed.", request.request_id)

    except Exception as e:
        logger.error("processing_error", extra={"request_id": request.request_id, "error": str(e)})
        return error_response("PROCESSING_FAILED", False, "An error occurred during processing.", request.request_id)

    finally:
        # Cleanup prepared audio based on retention policy
        if prepared_path and prepared_path.exists():
            if PREPARED_AUDIO_RETENTION == "ephemeral":
                try:
                    prepared_path.unlink()
                except OSError:
                    pass


@app.get("/health")
async def health():
    """Health check endpoint."""
    return {"status": "ok"}
