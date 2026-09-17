"""Tests for bearer token authentication (worker/auth.py)."""

import pytest
from unittest.mock import patch, MagicMock
from fastapi import HTTPException
from fastapi.security import HTTPAuthorizationCredentials

from worker.auth import verify_token


class TestVerifyToken:
    """Test bearer token verification."""

    @pytest.mark.asyncio
    async def test_correct_token_accepted(self):
        """Correct token is accepted."""
        with patch("worker.config.WORKER_TOKEN", "test-secret-123"):
            creds = HTTPAuthorizationCredentials(
                scheme="Bearer",
                credentials="test-secret-123",
            )
            result = await verify_token(credentials=creds)
            assert result == "test-secret-123"

    @pytest.mark.asyncio
    async def test_incorrect_token_rejected(self):
        """Incorrect token is rejected with 403."""
        with patch("worker.config.WORKER_TOKEN", "test-secret-123"):
            creds = HTTPAuthorizationCredentials(
                scheme="Bearer",
                credentials="wrong-token",
            )
            with pytest.raises(HTTPException) as exc_info:
                await verify_token(credentials=creds)
            assert exc_info.value.status_code == 403
            assert exc_info.value.detail == "Invalid token"

    @pytest.mark.asyncio
    async def test_missing_credentials_rejected(self):
        """Missing credentials are rejected with 401."""
        with patch("worker.config.WORKER_TOKEN", "test-secret-123"):
            with pytest.raises(HTTPException) as exc_info:
                await verify_token(credentials=None)
            assert exc_info.value.status_code == 401
            assert exc_info.value.detail == "Missing authorization header"

    @pytest.mark.asyncio
    async def test_unconfigured_token_rejected(self):
        """Unconfigured token (empty) is rejected with 500."""
        with patch("worker.config.WORKER_TOKEN", ""):
            with pytest.raises(HTTPException) as exc_info:
                await verify_token(credentials=None)
            assert exc_info.value.status_code == 500
            assert exc_info.value.detail == "Worker token not configured"
