"""Pytest configuration for RTFTT worker tests."""

import sys
from pathlib import Path

# Add parent directory to path so worker modules can be imported
sys.path.insert(0, str(Path(__file__).parent.parent))
