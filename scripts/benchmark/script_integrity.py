"""Comprehensive Unicode script-integrity analysis for benchmark transcripts.

This module provides HEURISTIC flags only. It must NOT be used to claim
semantic correctness. Script presence/absence is an indicator, not proof
of transcript quality. Names, numbers, acronyms, and legitimate borrowed
material can legitimately appear in other scripts.

Correction history:
- Original detector (in run_expanded_benchmark.py) checked only CJK presence
  and Tamil-script ratio. It missed Hebrew, Cyrillic, Korean (Hangul),
  Arabic, Gurmukhi, and other scripts, and auto-passed empty transcripts
  as "clean". This was a real evidence-integrity defect (post-escalation
  independent review, BLOCKER-1).
- This module checks all major script classes and explicitly flags empty
  output.
"""

from collections import Counter

# Unicode script ranges (major scripts)
SCRIPT_RANGES: dict[str, list[tuple[str, str]]] = {
    "Tamil": [("\u0B80", "\u0BFF")],
    "Latin": [
        ("\u0041", "\u005A"),
        ("\u0061", "\u007A"),
        ("\u00C0", "\u024F"),
        ("\u1E00", "\u1EFF"),
    ],
    "CJK": [("\u4E00", "\u9FFF"), ("\u3400", "\u4DBF"), ("\u3040", "\u30FF")],
    "Devanagari": [("\u0900", "\u097F")],
    "Gurmukhi": [("\u0A00", "\u0A7F")],
    "Bengali": [("\u0980", "\u09FF")],
    "Gujarati": [("\u0A80", "\u0AFF")],
    "Telugu": [("\u0C00", "\u0C7F")],
    "Kannada": [("\u0C80", "\u0CFF")],
    "Malayalam": [("\u0D00", "\u0D7F")],
    "Hebrew": [("\u0590", "\u05FF")],
    "Arabic": [("\u0600", "\u06FF"), ("\u0750", "\u077F")],
    "Cyrillic": [("\u0400", "\u04FF")],
    "Greek": [("\u0370", "\u03FF")],
    "Korean": [("\uAC00", "\uD7AF"), ("\u1100", "\u11FF")],
    "Thai": [("\u0E00", "\u0E7F")],
    "Sinhala": [("\u0D80", "\u0DFF")],
    "Georgian": [("\u10A0", "\u10FF")],
    "Armenian": [("\u0530", "\u058F")],
    "Ethiopic": [("\u1200", "\u137F")],
    "Khmer": [("\u1780", "\u17FF")],
    "Lao": [("\u0E80", "\u0EFF")],
    "Myanmar": [("\u1000", "\u109F")],
    "Tibetan": [("\u0F00", "\u0FFF")],
}

# Scripts treated as expected/non-flagged for a given language expectation.
# Latin is always tolerated because of names, numbers, acronyms, and
# legitimate borrowing.
EXPECTED_BY_LANGUAGE: dict[str, set[str]] = {
    "ta": {"Tamil", "Latin"},
    "ms": {"Latin"},
    "en": {"Latin"},
    "zh": {"CJK", "Latin"},
    "mixed": {"Latin", "Tamil", "CJK"},
}


def classify_char(c: str) -> str:
    """Classify a character into a script name, or 'Other'."""
    for script, ranges in SCRIPT_RANGES.items():
        for lo, hi in ranges:
            if lo <= c <= hi:
                return script
    return "Other"


def expected_scripts_for(alias: str) -> set[str]:
    """Determine expected scripts from a sample alias."""
    if "ta_in" in alias:
        return EXPECTED_BY_LANGUAGE["ta"]
    if "ms_my" in alias:
        return EXPECTED_BY_LANGUAGE["ms"]
    if "en_us" in alias:
        return EXPECTED_BY_LANGUAGE["en"]
    if "cmn" in alias:
        return EXPECTED_BY_LANGUAGE["zh"]
    return EXPECTED_BY_LANGUAGE["mixed"]


def analyze_transcript(transcript: str, expected: set[str]) -> dict:
    """Analyze a transcript for script composition and integrity heuristics.

    Returns a dict with:
    - counts: per-script character counts
    - unexpected_scripts: scripts present but not expected (excluding Other)
    - empty: True if the transcript has no non-whitespace characters
    - flags: list of human-readable heuristic flags
    - clean: heuristic boolean (NOT a semantic-correctness claim)

    IMPORTANT: `clean` is a heuristic only. It must not be used to claim
    semantic correctness. Empty transcripts are flagged, never passed.
    """
    if not transcript or not transcript.strip():
        return {
            "counts": {},
            "unexpected_scripts": {},
            "empty": True,
            "flags": ["Empty transcript (no content produced)"],
            "clean": False,
        }

    counts: Counter[str] = Counter()
    for c in transcript:
        if c.strip():
            counts[classify_char(c)] += 1

    unexpected = {
        s: n
        for s, n in counts.items()
        if s not in expected and s not in ("Other",) and n > 0
    }

    flags: list[str] = []
    if unexpected:
        flags.append(f"Unexpected script(s): {unexpected}")

    return {
        "counts": dict(counts),
        "unexpected_scripts": unexpected,
        "empty": False,
        "flags": flags,
        "clean": len(flags) == 0,
    }
