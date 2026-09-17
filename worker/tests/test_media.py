"""Tests for media path security (worker/media.py)."""

import os
import tempfile
from pathlib import Path
from unittest.mock import patch

import pytest

from worker.media import MediaAccessError, resolve_media_path


class TestResolveMediaPath:
    """Test media path resolution and security."""

    def test_valid_relative_path(self, tmp_path):
        """Valid opaque relative reference resolves correctly."""
        media_file = tmp_path / "media" / "test.mp3"
        media_file.parent.mkdir(parents=True)
        media_file.write_bytes(b"fake audio")

        with patch.object(
            __import__("worker.config", fromlist=["config"]),
            "SHARED_MEDIA_ROOT",
            tmp_path,
        ):
            from worker import config
            original = config.SHARED_MEDIA_ROOT
            config.SHARED_MEDIA_ROOT = tmp_path
            try:
                result = resolve_media_path("media/test.mp3")
                assert result == media_file
            finally:
                config.SHARED_MEDIA_ROOT = original

    def test_absolute_posix_path_rejected(self):
        """Absolute POSIX path is rejected."""
        with pytest.raises(MediaAccessError, match="absolute path"):
            resolve_media_path("/etc/passwd")

    def test_absolute_windows_path_rejected(self):
        """Absolute Windows path is rejected."""
        with pytest.raises(MediaAccessError, match="absolute path"):
            resolve_media_path("C:\\Windows\\file.mp3")

    def test_unc_path_rejected(self):
        """UNC path is rejected."""
        with pytest.raises(MediaAccessError, match="absolute path"):
            resolve_media_path("\\\\server\\share\\file.mp3")

    def test_traversal_rejected(self):
        """Path traversal (..) is rejected."""
        with pytest.raises(MediaAccessError, match="path traversal"):
            resolve_media_path("media/../../../etc/passwd")

    def test_path_escape_rejected(self, tmp_path):
        """Resolved path escaping shared root is rejected."""
        outside = tmp_path / "outside"
        outside.mkdir()
        (outside / "file.mp3").write_bytes(b"fake")

        with pytest.raises(MediaAccessError, match="escapes shared root"):
            from worker import config
            original = config.SHARED_MEDIA_ROOT
            config.SHARED_MEDIA_ROOT = tmp_path / "media"
            try:
                resolve_media_path(str(outside / "file.mp3"))
            finally:
                config.SHARED_MEDIA_ROOT = original

    def test_nonexistent_file_rejected(self, tmp_path):
        """Non-existent file is rejected."""
        from worker import config
        original = config.SHARED_MEDIA_ROOT
        config.SHARED_MEDIA_ROOT = tmp_path
        try:
            with pytest.raises(MediaAccessError, match="not found"):
                resolve_media_path("nonexistent.mp3")
        finally:
            config.SHARED_MEDIA_ROOT = original
