"""Pytest configuration for RTFTT worker tests."""

import sys
from pathlib import Path

# worker/tests/conftest.py -> worker/tests -> worker -> repo root.
# The repository root (parent of the `worker` package) must be on sys.path
# so `import worker.<module>` resolves `worker` as a package, matching how
# the worker's own modules import each other (`from . import config`).
REPO_ROOT = Path(__file__).resolve().parent.parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))
