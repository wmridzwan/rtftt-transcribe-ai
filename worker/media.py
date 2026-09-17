"""Shared filesystem media access with traversal rejection."""

import os
from pathlib import Path

from . import config


class MediaAccessError(Exception):
    """Raised when media access is rejected."""

    pass


def resolve_media_path(storage_key: str) -> Path:
    """
    Resolve a storage key to an absolute path beneath the shared media root.

    Rules:
    - Reject absolute paths
    - Reject path traversal (..)
    - Resolve only beneath configured shared-media root
    """
    if os.path.isabs(storage_key):
        raise MediaAccessError("Media reference rejected: absolute path not allowed.")

    if ".." in storage_key:
        raise MediaAccessError("Media reference rejected: path traversal not allowed.")

    resolved = (config.SHARED_MEDIA_ROOT / storage_key).resolve()

    if not str(resolved).startswith(str(config.SHARED_MEDIA_ROOT.resolve())):
        raise MediaAccessError("Media reference rejected: path escapes shared root.")

    if not resolved.exists():
        raise MediaAccessError("Media file not found.")

    return resolved
