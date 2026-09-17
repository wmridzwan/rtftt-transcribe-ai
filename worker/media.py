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
    # os.path.isabs() is platform-dependent and, on Windows, does not treat
    # a POSIX-style leading slash with no drive letter as absolute. Check
    # explicitly so rejection does not depend on host OS quirks.
    if storage_key.startswith("/") or storage_key.startswith("\\") or os.path.isabs(storage_key):
        raise MediaAccessError("Media reference rejected: absolute path not allowed.")

    if ".." in storage_key:
        raise MediaAccessError("Media reference rejected: path traversal not allowed.")

    shared_root = config.SHARED_MEDIA_ROOT.resolve()
    resolved = (shared_root / storage_key).resolve()

    if resolved != shared_root and shared_root not in resolved.parents:
        raise MediaAccessError("Media reference rejected: path escapes shared root.")

    if not resolved.exists():
        raise MediaAccessError("Media file not found.")

    return resolved
