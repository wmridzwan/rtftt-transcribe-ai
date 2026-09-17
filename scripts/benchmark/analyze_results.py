#!/usr/bin/env python3
"""Analyze expanded benchmark results for gate decision."""
import json
from pathlib import Path

RESULTS_DIR = Path(__file__).parent.parent.parent / "benchmark-media" / "results"

for model in ["turbo", "large-v3"]:
    path = RESULTS_DIR / f"expanded_benchmark_{model}.json"
    with open(path, "r", encoding="utf-8") as f:
        data = json.load(f)

    agg = data["aggregate"]
    print(f"\n{'='*60}")
    print(f"{model} ({data['model_size']})")
    print(f"{'='*60}")
    print(f"Samples: {agg['total_samples']}")
    print(f"Mean RTF: {agg['mean_rtf']}")
    print(f"Tamil total: {agg['tamil_total']}")
    print(f"Tamil corrupted: {agg['tamil_corrupted']}")
    if agg.get("tamil_corruption_details"):
        for d in agg["tamil_corruption_details"]:
            print(f"  {d['alias']}: {d['flags']}")

    # Show Tamil per-sample results
    tamil_samples = [s for s in data["samples"]
                     if s.get("config") == "ta_in" or "ta_in" in s.get("alias", "")]
    print(f"\nTamil per-sample:")
    for s in tamil_samples:
        corrupt = "CLEAN" if s["script_integrity"]["clean"] else f"CORRUPTED: {s['script_integrity']['flags']}"
        print(f"  {s['alias']}: RTF={s['rtf']:.2f}, lang={s['detected_language']}, {corrupt}")

    # Show mixed samples
    mixed = [s for s in data["samples"] if "MIX" in s.get("alias", "")]
    print(f"\nMixed per-sample:")
    for s in mixed:
        corrupt = "CLEAN" if s["script_integrity"]["clean"] else f"FLAGS: {s['script_integrity']['flags']}"
        print(f"  {s['alias']}: RTF={s['rtf']:.2f}, lang={s['detected_language']}, {corrupt}")
