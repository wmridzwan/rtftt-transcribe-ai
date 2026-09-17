#!/usr/bin/env python3
"""
RTFTT Phase 3 Benchmark Gate: Turbo vs Large-v3.

Optimized for CPU-only execution with incremental result saving.
"""

import json
import time
import sys
from pathlib import Path

WORKER_DIR = Path(__file__).parent.parent / "worker"
sys.path.insert(0, str(WORKER_DIR))

from faster_whisper import WhisperModel

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
RESULTS_DIR = BENCHMARK_DIR / "results"

MODELS = {
    "turbo": {"size": "large-v3-turbo", "device": "cpu", "compute_type": "int8"},
    "large-v3": {"size": "large-v3", "device": "cpu", "compute_type": "int8"},
}


def run_benchmark(model_name: str, model_config: dict) -> dict:
    print(f"\n{'='*60}")
    print(f"Loading model: {model_name} ({model_config['size']})")
    print(f"{'='*60}")

    start_load = time.perf_counter()
    model = WhisperModel(
        model_config["size"],
        device=model_config["device"],
        compute_type=model_config["compute_type"],
    )
    load_time = time.perf_counter() - start_load
    print(f"Model loaded in {load_time:.1f}s")

    manifest_path = BENCHMARK_DIR / "manifest.json"
    with open(manifest_path, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    result_path = RESULTS_DIR / f"benchmark_{model_name}.json"
    results = {
        "model": model_name,
        "model_size": model_config["size"],
        "device": model_config["device"],
        "compute_type": model_config["compute_type"],
        "load_time_seconds": round(load_time, 2),
        "samples": [],
    }

    for i, sample in enumerate(manifest["samples"]):
        alias = sample["alias"]
        audio_path = BENCHMARK_DIR / sample["local_file"]

        if not audio_path.exists():
            print(f"  SKIP {alias}: file not found")
            continue

        print(f"  [{i+1}/{len(manifest['samples'])}] {alias} ({sample['duration_seconds']:.1f}s)...", end=" ", flush=True)

        start_infer = time.perf_counter()
        segments, info = model.transcribe(
            str(audio_path),
            beam_size=5,
            language=None,
        )
        segment_list = list(segments)
        infer_time = time.perf_counter() - start_infer

        transcript = " ".join(s.text.strip() for s in segment_list)
        detected_lang = info.language if info.language else "und"

        sample_result = {
            "alias": alias,
            "duration_seconds": sample["duration_seconds"],
            "detected_language": detected_lang,
            "inference_time_seconds": round(infer_time, 3),
            "rtf": round(infer_time / sample["duration_seconds"], 4) if sample["duration_seconds"] > 0 else None,
            "segment_count": len(segment_list),
            "transcript": transcript[:500],
            "language_name": sample.get("language_name", ""),
            "dataset": sample.get("dataset", ""),
        }
        results["samples"].append(sample_result)

        # Save incrementally
        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(results, f, indent=2, ensure_ascii=False)

        rtf = sample_result["rtf"]
        print(f"done ({infer_time:.1f}s, RTF={rtf:.4f}, lang={detected_lang})")

    # Aggregate
    rtfs = [r["rtf"] for r in results["samples"] if r["rtf"] is not None]
    durations = [r["duration_seconds"] for r in results["samples"]]
    infer_times = [r["inference_time_seconds"] for r in results["samples"]]

    results["aggregate"] = {
        "total_samples": len(results["samples"]),
        "total_audio_seconds": round(sum(durations), 2),
        "total_inference_seconds": round(sum(infer_times), 2),
        "mean_rtf": round(sum(rtfs) / len(rtfs), 4) if rtfs else None,
        "max_rtf": round(max(rtfs), 4) if rtfs else None,
        "min_rtf": round(min(rtfs), 4) if rtfs else None,
    }

    with open(result_path, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    return results


def main():
    RESULTS_DIR.mkdir(parents=True, exist_ok=True)

    all_results = {}
    for model_name, model_config in MODELS.items():
        results = run_benchmark(model_name, model_config)
        all_results[model_name] = results

    # Comparison
    print(f"\n{'='*60}")
    print("BENCHMARK GATE COMPARISON")
    print(f"{'='*60}")

    for model_name, results in all_results.items():
        agg = results["aggregate"]
        print(f"\n{model_name} ({results['model_size']}):")
        print(f"  Samples: {agg['total_samples']}")
        print(f"  Total audio: {agg['total_audio_seconds']}s")
        print(f"  Total inference: {agg['total_inference_seconds']}s")
        print(f"  Mean RTF: {agg['mean_rtf']}")
        print(f"  RTF range: [{agg['min_rtf']}, {agg['max_rtf']}]")

    turbo_rtf = all_results.get("turbo", {}).get("aggregate", {}).get("mean_rtf")
    large_rtf = all_results.get("large-v3", {}).get("aggregate", {}).get("mean_rtf")

    print(f"\n{'='*60}")
    print("GATE DECISION")
    print(f"{'='*60}")
    if turbo_rtf and large_rtf:
        speedup = large_rtf / turbo_rtf
        print(f"Turbo mean RTF: {turbo_rtf}")
        print(f"Large-v3 mean RTF: {large_rtf}")
        print(f"Turbo is {speedup:.2f}x faster than Large-v3")
        if speedup > 1.0:
            print("RECOMMENDATION: turbo (faster, acceptable quality)")
        else:
            print("RECOMMENDATION: large-v3 (better or equivalent speed)")
    else:
        print("Insufficient data for comparison")

    comparison_path = RESULTS_DIR / "benchmark_comparison.json"
    with open(comparison_path, "w", encoding="utf-8") as f:
        json.dump(all_results, f, indent=2, ensure_ascii=False)
    print(f"\nComparison saved to {comparison_path}")


if __name__ == "__main__":
    main()
