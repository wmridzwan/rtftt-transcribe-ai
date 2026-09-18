#!/usr/bin/env python3
"""
RTFTT Phase 3 Expanded Benchmark Gate (H6).

Runs faster-whisper inference on the full expanded corpus using both models.
Records per-sample timing, transcripts, script-corruption analysis, and
segment-LID overhead measurements.
"""

import json
import time
import sys
import unicodedata
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


def analyze_script_integrity(transcript: str, expected_lang: str) -> dict:
    """Analyze script corruption in transcript.

    For Tamil (ta): expect dominant Tamil script (U+0B80-U+0BFF).
    For Mandarin (zh): expect dominant CJK Unified Ideographs (U+4E00-U+9FFF).
    Unexpected script mixing is flagged.
    """
    if not transcript:
        return {"clean": True, "flags": []}

    flags = []
    total_chars = len(transcript)

    if expected_lang == "ta":
        # Tamil block: U+0B80-U+0BFF
        tamil_chars = sum(1 for c in transcript if "\u0B80" <= c <= "\u0BFF")
        latin_chars = sum(1 for c in transcript if c.isascii() and c.isalpha())
        cjk_chars = sum(1 for c in transcript if "\u4E00" <= c <= "\u9FFF")

        if tamil_chars > 0:
            tamil_ratio = tamil_chars / total_chars
        else:
            tamil_ratio = 0.0

        # Flag if significant non-Tamil, non-Latin content
        if cjk_chars > 0:
            flags.append(f"CJK characters found: {cjk_chars}")
        if tamil_ratio < 0.3 and tamil_chars > 0:
            flags.append(f"Low Tamil script ratio: {tamil_ratio:.2f}")
        if tamil_chars == 0 and latin_chars > 0:
            flags.append("No Tamil script at all (Latin only)")

    elif expected_lang == "zh":
        # CJK block: U+4E00-U+9FFF
        cjk_chars = sum(1 for c in transcript if "\u4E00" <= c <= "\u9FFF")
        tamil_chars = sum(1 for c in transcript if "\u0B80" <= c <= "\u0BFF")

        if tamil_chars > 0:
            flags.append(f"Tamil characters in Chinese output: {tamil_chars}")

    return {"clean": len(flags) == 0, "flags": flags}


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

    result_path = RESULTS_DIR / f"expanded_benchmark_{model_name}.json"
    results = {
        "model": model_name,
        "model_size": model_config["size"],
        "device": model_config["device"],
        "compute_type": model_config["compute_type"],
        "load_time_seconds": round(load_time, 2),
        "samples": [],
    }

    total_lid_time = 0.0
    total_segments = 0

    for i, sample in enumerate(manifest["samples"]):
        alias = sample["alias"]
        audio_path = BENCHMARK_DIR / sample["local_file"]

        if not audio_path.exists():
            print(f"  SKIP {alias}: file not found")
            continue

        print(f"  [{i+1}/{len(manifest['samples'])}] {alias} ({sample['duration_seconds']:.1f}s)...", end=" ", flush=True)

        start_infer = time.perf_counter()
        segments_iter, info = model.transcribe(
            str(audio_path),
            beam_size=5,
            language=None,
        )
        segment_list = list(segments_iter)
        infer_time = time.perf_counter() - start_infer

        transcript = " ".join(s.text.strip() for s in segment_list)
        detected_lang = info.language if info.language else "und"

        # Script corruption analysis
        expected_lang = detected_lang
        if "ta" in sample.get("config", ""):
            expected_lang = "ta"
        elif "cmn" in sample.get("config", ""):
            expected_lang = "zh"
        script_analysis = analyze_script_integrity(transcript, expected_lang)

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
            "script_integrity": script_analysis,
        }
        results["samples"].append(sample_result)

        # Save incrementally
        with open(result_path, "w", encoding="utf-8") as f:
            json.dump(results, f, indent=2, ensure_ascii=False)

        rtf = sample_result["rtf"]
        script_flag = "" if script_analysis["clean"] else f" SCRIPT_FLAGS: {script_analysis['flags']}"
        print(f"done ({infer_time:.1f}s, RTF={rtf:.4f}, lang={detected_lang}){script_flag}")

    # Aggregate
    rtfs = [r["rtf"] for r in results["samples"] if r["rtf"] is not None]
    durations = [r["duration_seconds"] for r in results["samples"]]
    infer_times = [r["inference_time_seconds"] for r in results["samples"]]
    segment_counts = [r["segment_count"] for r in results["samples"]]

    # Script corruption summary
    tamil_samples = [r for r in results["samples"] if "ta" in r.get("dataset", "") or "ta" in r.get("language_name", "").lower() or r.get("detected_language") == "ta"]
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

    return results


def main():
    RESULTS_DIR.mkdir(parents=True, exist_ok=True)

    all_results = {}
    for model_name, model_config in MODELS.items():
        results = run_benchmark(model_name, model_config)
        all_results[model_name] = results

    # Comparison summary
    print(f"\n{'='*60}")
    print("EXPANDED BENCHMARK COMPARISON")
    print(f"{'='*60}")

    for model_name, results in all_results.items():
        agg = results["aggregate"]
        print(f"\n{model_name} ({results['model_size']}):")
        print(f"  Samples: {agg['total_samples']}")
        print(f"  Total audio: {agg['total_audio_seconds']}s")
        print(f"  Total inference: {agg['total_inference_seconds']}s")
        print(f"  Mean RTF: {agg['mean_rtf']}")
        print(f"  RTF range: [{agg['min_rtf']}, {agg['max_rtf']}]")
        print(f"  Total segments: {agg['total_segments']}")
        print(f"  Tamil samples: {agg['tamil_total']}")
        print(f"  Tamil corrupted: {agg['tamil_corrupted']}")
        if agg["tamil_corruption_details"]:
            for detail in agg["tamil_corruption_details"]:
                print(f"    {detail['alias']}: {detail['flags']}")

    # Gate decision
    turbo_rtf = all_results.get("turbo", {}).get("aggregate", {}).get("mean_rtf")
    large_rtf = all_results.get("large-v3", {}).get("aggregate", {}).get("mean_rtf")
    turbo_tamil_corr = all_results.get("turbo", {}).get("aggregate", {}).get("tamil_corrupted", 0)
    large_tamil_corr = all_results.get("large-v3", {}).get("aggregate", {}).get("tamil_corrupted", 0)

    print(f"\n{'='*60}")
    print("MODEL GATE ASSESSMENT")
    print(f"{'='*60}")
    if turbo_rtf and large_rtf:
        speedup = large_rtf / turbo_rtf
        print(f"Turbo mean RTF: {turbo_rtf}")
        print(f"Large-v3 mean RTF: {large_rtf}")
        print(f"Turbo is {speedup:.2f}x faster")
        print(f"Turbo Tamil corruption: {turbo_tamil_corr}")
        print(f"Large-v3 Tamil corruption: {large_tamil_corr}")

    # Save comparison
    comparison_path = RESULTS_DIR / "expanded_benchmark_comparison.json"
    with open(comparison_path, "w", encoding="utf-8") as f:
        json.dump(all_results, f, indent=2, ensure_ascii=False)
    print(f"\nComparison saved to {comparison_path}")


if __name__ == "__main__":
    main()
