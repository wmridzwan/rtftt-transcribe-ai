"""Tests for FFmpeg audio preparation (worker/ffmpeg.py)."""

import subprocess
from pathlib import Path
from unittest.mock import patch, MagicMock

import pytest

from worker.ffmpeg import FfmpegError, prepare_audio


class TestPrepareAudio:
    """Test FFmpeg audio preparation."""

    def test_successful_preparation(self, tmp_path):
        """Successful FFmpeg invocation produces output file."""
        input_file = tmp_path / "input.mp3"
        input_file.write_bytes(b"fake audio data")

        with patch("worker.ffmpeg.config") as mock_config:
            mock_config.PREPARED_AUDIO_DIR = tmp_path / "prepared"
            mock_config.PREPARED_AUDIO_DIR.mkdir(parents=True, exist_ok=True)
            mock_config.MAX_WORKER_TIMEOUT = 300

            with patch("subprocess.run") as mock_run:
                mock_run.return_value = MagicMock(
                    returncode=0,
                    stdout="",
                    stderr="",
                )

                # Create the expected output file
                def side_effect(cmd, **kwargs):
                    output_path = Path(cmd[-1])
                    output_path.write_bytes(b"prepared audio")
                    return MagicMock(returncode=0, stdout="", stderr="")

                mock_run.side_effect = side_effect

                result = prepare_audio(input_file)
                assert result.exists()
                assert result.suffix == ".wav"

    def test_ffmpeg_failure_raises_error(self, tmp_path):
        """FFmpeg failure raises FfmpegError."""
        input_file = tmp_path / "input.mp3"
        input_file.write_bytes(b"fake audio data")

        with patch("worker.ffmpeg.config") as mock_config:
            mock_config.PREPARED_AUDIO_DIR = tmp_path / "prepared"
            mock_config.PREPARED_AUDIO_DIR.mkdir(parents=True, exist_ok=True)
            mock_config.MAX_WORKER_TIMEOUT = 300

            with patch("subprocess.run") as mock_run:
                mock_run.return_value = MagicMock(
                    returncode=1,
                    stdout="",
                    stderr="Error: invalid input",
                )

                with pytest.raises(FfmpegError, match="exited with code 1"):
                    prepare_audio(input_file)

    def test_timeout_raises_error(self, tmp_path):
        """FFmpeg timeout raises FfmpegError."""
        input_file = tmp_path / "input.mp3"
        input_file.write_bytes(b"fake audio data")

        with patch("worker.ffmpeg.config") as mock_config:
            mock_config.PREPARED_AUDIO_DIR = tmp_path / "prepared"
            mock_config.PREPARED_AUDIO_DIR.mkdir(parents=True, exist_ok=True)
            mock_config.MAX_WORKER_TIMEOUT = 1

            with patch("subprocess.run") as mock_run:
                mock_run.side_effect = subprocess.TimeoutExpired(cmd="ffmpeg", timeout=1)

                with pytest.raises(FfmpegError, match="timed out"):
                    prepare_audio(input_file)

    def test_ffmpeg_not_found_raises_error(self, tmp_path):
        """Missing FFmpeg raises FfmpegError."""
        input_file = tmp_path / "input.mp3"
        input_file.write_bytes(b"fake audio data")

        with patch("worker.ffmpeg.config") as mock_config:
            mock_config.PREPARED_AUDIO_DIR = tmp_path / "prepared"
            mock_config.PREPARED_AUDIO_DIR.mkdir(parents=True, exist_ok=True)
            mock_config.MAX_WORKER_TIMEOUT = 300

            with patch("subprocess.run") as mock_run:
                mock_run.side_effect = FileNotFoundError("ffmpeg not found")

                with pytest.raises(FfmpegError, match="not found"):
                    prepare_audio(input_file)
