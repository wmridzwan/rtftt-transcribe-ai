#!/usr/bin/env python3
"""Audit expanded benchmark evidence integrity."""
import json
from pathlib import Path

manifest = json.loads(Path("benchmark-media/manifest.json").read_text(encoding="utf-8"))
turbo = json.loads(Path("benchmark-media/results/expanded_benchmark_turbo.json").read_text(encoding="utf-8"))
large = json.loads(Path("benchmark-media/results/expanded_benchmark_large-v3.json").read_text(encoding="utf-8"))

tamil = [s for s in manifest["samples"] if s.get("config") == "ta_in"]
mixed = [s for s in manifest["samples"] if s.get("config") == "mixed"]
non_tamil = [s for s in manifest["samples"] if s.get("config") not in ("ta_in", "mixed")]

print(f"Corpus: {len(manifest['samples'])} total")
print(f"  Tamil (ta_in): {len(tamil)}")
print(f"  Mixed: {len(mixed)}")
print(f"  Non-Tamil FLEURS: {len(non_tamil)}")
print(f"Turbo results: {len(turbo['samples'])} samples")
print(f"Large-v3 results: {len(large['samples'])} samples")

turbo_tamil = [s for s in turbo["samples"] if "ta_in" in s.get("alias", "")]
turbo_tamil_clean = [s for s in turbo_tamil if s["script_integrity"]["clean"]]
print(f"Turbo Tamil: {len(turbo_tamil)} samples, {len(turbo_tamil_clean)} clean")

large_tamil = [s for s in large["samples"] if "ta_in" in s.get("alias", "")]
large_tamil_clean = [s for s in large_tamil if s["script_integrity"]["clean"]]
print(f"Large-v3 Tamil: {len(large_tamil)} samples, {len(large_tamil_clean)} clean")

tamil_refs = [s for s in tamil if s.get("reference_transcription")]
print(f"Tamil with reference: {len(tamil_refs)}/{len(tamil)}")

turbo_rtfs = [s["rtf"] for s in turbo["samples"] if s.get("rtf")]
large_rtfs = [s["rtf"] for s in large["samples"] if s.get("rtf")]
print(f"Turbo mean RTF (computed): {sum(turbo_rtfs)/len(turbo_rtfs):.4f}")
print(f"Large-v3 mean RTF (computed): {sum(large_rtfs)/len(large_rtfs):.4f}")

# Verify raw JSON files exist and have correct structure
for name in ["expanded_benchmark_turbo.json", "expanded_benchmark_large-v3.json"]:
    p = Path(f"benchmark-media/results/{name}")
    assert p.exists(), f"Missing: {p}"
    data = json.loads(p.read_text(encoding="utf-8"))
    assert "samples" in data, f"No samples in {name}"
    assert "aggregate" in data, f"No aggregate in {name}"
    for s in data["samples"]:
        assert "alias" in s
        assert "rtf" in s
        assert "script_integrity" in s
    print(f"  {name}: OK ({len(data['samples'])} samples, structure valid)")

print("\nAll evidence checks passed.")
