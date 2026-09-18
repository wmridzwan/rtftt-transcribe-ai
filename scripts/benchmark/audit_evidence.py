#!/usr/bin/env python3
"""Audit expanded benchmark evidence integrity using the corrected
script-integrity module.

Verifies corpus composition, raw result structure, reference coverage,
and recomputes corruption counts from raw transcripts (not the flawed
embedded script_integrity field).
"""
import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).parent.parent.parent
sys.path.insert(0, str(REPO_ROOT))

from scripts.benchmark.script_integrity import analyze_transcript, expected_scripts_for

RESULTS_DIR = REPO_ROOT / "benchmark-media" / "results"
manifest = json.loads((REPO_ROOT / "benchmark-media" / "manifest.json").read_text(encoding="utf-8"))


def load(model):
    return json.loads((RESULTS_DIR / f"expanded_benchmark_{model}.json").read_text(encoding="utf-8"))


def main():
    tamil = [s for s in manifest["samples"] if s.get("config") == "ta_in"]
    mixed = [s for s in manifest["samples"] if s.get("config") == "mixed"]
    non_tamil = [s for s in manifest["samples"] if s.get("config") not in ("ta_in", "mixed")]

    print(f"Corpus: {len(manifest['samples'])} total")
    print(f"  Tamil (ta_in): {len(tamil)}")
    print(f"  Mixed: {len(mixed)}")
    print(f"  Non-Tamil FLEURS: {len(non_tamil)}")

    for model in ["turbo", "large-v3"]:
        data = load(model)
        print(f"\n{model}: {len(data['samples'])} samples")

        tamil_entries = [s for s in data["samples"] if "ta_in" in s["alias"]]
        bad = []
        for s in tamil_entries:
            result = analyze_transcript(s.get("transcript", ""), expected_scripts_for(s["alias"]))
            if not result["clean"]:
                bad.append((s["alias"], result["unexpected_scripts"], result["empty"]))
        print(f"  Tamil: {len(tamil_entries)} samples, {len(bad)} corrupted")
        for alias, scripts, empty in bad:
            print(f"    {alias}: scripts={scripts} empty={empty}")

        # Verify raw structure
        for s in data["samples"]:
            assert "alias" in s and "rtf" in s and "transcript" in s

    # Reference coverage
    refs = [s for s in tamil if s.get("reference_transcription")]
    print(f"\nTamil with reference transcription: {len(refs)}/{len(tamil)}")

    # Arithmetic
    turbo = load("turbo")
    large = load("large-v3")
    turbo_rtf = sum(s["rtf"] for s in turbo["samples"] if s.get("rtf")) / len(turbo["samples"])
    large_rtf = sum(s["rtf"] for s in large["samples"] if s.get("rtf")) / len(large["samples"])
    print(f"Turbo mean RTF (computed): {turbo_rtf:.4f}")
    print(f"Large-v3 mean RTF (computed): {large_rtf:.4f}")
    print(f"Speedup ratio: {large_rtf / turbo_rtf:.4f}x")

    print("\nAudit complete.")


if __name__ == "__main__":
    main()