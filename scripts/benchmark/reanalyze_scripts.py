#!/usr/bin/env python3
"""Re-analyze raw benchmark transcripts with the corrected script-integrity
module (all script classes; explicit empty-output flagging).

Reads the raw per-sample transcripts already produced by the expanded
benchmark and regenerates the derived script-integrity analysis. Does NOT
re-run model inference (raw transcripts already exist).

Outputs a UTF-8 JSON report and an ASCII-safe console summary.
"""
import json
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).parent.parent.parent
sys.path.insert(0, str(REPO_ROOT))

from scripts.benchmark.script_integrity import analyze_transcript, expected_scripts_for

RESULTS_DIR = REPO_ROOT / "benchmark-media" / "results"
OUT_PATH = RESULTS_DIR / "script_corruption_analysis.json"


def main():
    report = {}
    for model in ["turbo", "large-v3"]:
        path = RESULTS_DIR / f"expanded_benchmark_{model}.json"
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        model_report = {"samples": [], "corrupted_samples": [], "empty_samples": []}
        for s in data["samples"]:
            alias = s["alias"]
            transcript = s.get("transcript", "")
            expected = expected_scripts_for(alias)
            result = analyze_transcript(transcript, expected)

            entry = {
                "alias": alias,
                "config": s.get("config", ""),
                "detected_language": s.get("detected_language"),
                "rtf": s.get("rtf"),
                "script_counts": result["counts"],
                "unexpected_scripts": result["unexpected_scripts"],
                "empty": result["empty"],
                "flags": result["flags"],
                "clean": result["clean"],
                "transcript": transcript,
            }
            model_report["samples"].append(entry)
            if result["empty"]:
                model_report["empty_samples"].append(alias)
            elif not result["clean"]:
                model_report["corrupted_samples"].append(alias)

        # Real Tamil (ta_in) corruption counts
        tamil = [e for e in model_report["samples"] if "ta_in" in e["alias"]]
        tamil_bad = [e["alias"] for e in tamil if not e["clean"]]
        model_report["tamil_total"] = len(tamil)
        model_report["tamil_corrupted"] = tamil_bad

        report[model] = model_report

    with open(OUT_PATH, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)

    for model, mr in report.items():
        print(
            f"{model}: tamil {len(mr['tamil_corrupted'])}/{mr['tamil_total']} corrupted "
            f"-> {mr['tamil_corrupted']}; "
            f"empty: {mr['empty_samples']}; "
            f"all flagged: {mr['corrupted_samples']}"
        )
    print(f"\nDetailed report: {OUT_PATH}")


if __name__ == "__main__":
    main()