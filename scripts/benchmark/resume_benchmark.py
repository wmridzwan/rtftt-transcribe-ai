#!/usr/bin/env python3
"""
Resume expanded benchmark: skips samples already present in the partial
results file, processes the rest, then recomputes the aggregate.
"""

import json
import sys
import time
from pathlib import Path

WORKER_DIR = Path(__file__).parent.parent.parent / "worker"
sys.path.insert(0, str(WORKER_DIR))

sys.path.insert(0, str(Path(__file__).parent))
from run_expanded_benchmark import analyze_script_integrity, MODELS

from faster_whisper import WhisperModel

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
RESULTS_DIR = BENCHMARK_DIR / "results"


def resume_model(model_name: str) -> dict:
    model_config = MODELS[model_name]
    result_path = RESULTS_DIR / f"expanded_benchmark_{model_name}.json"

    with open(result_path, "r", encoding="utf-8") as f:
        results = json.load(f)

    done_aliases = {s["alias"] for s in results["samples"]}
    print(f"Resuming {model_name}: {len(done_aliases)} samples already done")

    manifest_path = BENCHMARK_DIR / "manifest.json"
    with open(manifest_path, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    remaining = [s for s in manifest["samples"] if s["alias"] not in done_aliases]
    print(f"Remaining: {len(remaining)} samples")

    if not remaining:
        print("Nothing to do")
        return results

    print("Loading model...")
    model = WhisperModel(
        model_config["size"],
        device=model_config["device"],
        compute_type=model_config["compute_type"],
    )
    print("Model loaded")

    for i, sample in enumerate(remaining):
        alias = sample["alias"]
        audio_path = BENCHMARK_DIR / sample["local_file"]
        if not audio_path.exists():
            print(f"  SKIP {alias}: file not found")
            continue

        print(f"  [{len(done_aliases)+i+1}/{len(manifest['samples'])}] {alias} ({sample['duration_seconds']:.1f}s)...", end=" ", flush=True)

        start_infer = time.perf_counter()
        segments_iter, info = model.transcribe(str(audio_path), beam_size=5, language=None)
        segment_list = list(segments_iter)
        infer_time = time.perf_counter() - start_infer

        transcript = " ".join(s.text.strip() for s in segment_list)
        detected_lang = info.language if info.language else "und"

        expected_lang = detected_lang
        if "ta" in sample.get("config", ""):
            expected_lang = "ta"
        elif "cmn" in sample.get("config", ""):
            expected_lang = "zh"
        script_analysis = analyze_script_integrity(transcript, expected_lang)

        results["samples"].append({
            "alias": alias,
            "duration_seconds": sample["duration_seconds"],
            "detected_language": detected_lang,
            "inference_time_seconds": round(infer_time, 3),
            "rtf": round(infer_time / sample["duration_seconds"], 4) if sample["duration_seconds"] > 0 else None,
            "segment_count": len(segment_list),
            "transcript": transcript[:500],
            "language_name": sample.get("language_name", ""),
            "dataset": sample.get("dataset", ""),
            "script_integrity": script_analysis,
        })

        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(results, f, indent=2, ensure_ascii=False)

        print(f"done ({infer_time:.1f}s)")

    # Recompute aggregate
    rtfs = [r["rtf"] for r in results["samples"] if r["rtf"] is not None]
    durations = [r["duration_seconds"] for r in results["samples"]]
    infer_times = [r["inference_time_seconds"] for r in results["samples"]]
    segment_counts = [r["segment_count"] for r in results["samples"]]
    tamil_samples = [r for r in results["samples"] if "ta" in r.get("dataset", "") or r.get("detected_language") == "ta"]
    tamil_corrupted = [r for r in tamil_samples if not r["script_integrity"]["clean"]]

    results["aggregate"] = {
        "total_samples": len(results["samples"]),
        "total_audio_seconds": round(sum(durations), 2),
        "total_inference_seconds": round(sum(infer_times), 2),
        "mean_rtf": round(sum(rtfs) / len(rtfs), 4) if rtfs else None,
        "max_rtf": round(max(rtfs), 4) if rtfs else None,
        "min_rtf": round(min(rtfs), 4) if rtfs else None,
        "total_segments": sum(segment_counts),
        "mean_segments_per_sample": round(sum(segment_counts) / len(segment_counts), 1) if segment_counts else 0,
        "tamil_total": len(tamil_samples),
        "tamil_corrupted": len(tamil_corrupted),
        "tamil_corruption_details": [
            {"alias": r["alias"], "flags": r["script_integrity"]["flags"]}
            for r in tamil_corrupted
        ],
    }

    with open(result_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    agg = results["aggregate"]
    print(f"\n{model_name} complete: {agg['total_samples']} samples, mean RTF {agg['mean_rtf']}")
    print(f"Tamil: {agg['tamil_total']} total, {agg['tamil_corrupted']} corrupted")
    return results


if __name__ == "__main__":
    target = sys.argv[1] if len(sys.argv) > 1 else "turbo"
    resume_model(target)
