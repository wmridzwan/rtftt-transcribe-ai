"""Pytest configuration for benchmark tooling tests."""

import sys
from pathlib import Path

# scripts/benchmark/tests/conftest.py -> tests -> benchmark -> scripts -> repo root.
# The repository root must be on sys.path so `import scripts.benchmark.<module>`
# resolves as a package import.
REPO_ROOT = Path(__file__).resolve().parent.parent.parent.parent
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))
