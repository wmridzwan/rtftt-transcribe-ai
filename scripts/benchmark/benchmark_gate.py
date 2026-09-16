#!/usr/bin/env python3
"""
RTFTT Phase 3 Benchmark Gate: turbo vs large-v3

Requirements:
  pip install faster-whisper

Usage:
  python scripts/benchmark/benchmark_gate.py --media-dir ./benchmark-media

This script benchmarks faster-whisper turbo and large-v3 models on
representative multilingual media and outputs structured evidence.
"""

import argparse
import json
import os
import sys
import time
from pathlib import Path
from dataclasses import dataclass, asdict
from typing import Optional

SUPPORTED_EXTENSIONS = {'.mp3', '.wav', '.m4a', '.aac', '.flac', '.ogg', '.mp4', '.mov', '.webm'}


@dataclass
class BenchmarkResult:
    sample_id: str
    sample_description: str
    sample_duration_seconds: float
    model: str
    device: str
    compute_type: str
    processing_duration_seconds: float
    real_time_factor: Optional[float]
    text_length_chars: int
    segment_count: int
    detected_language: str
    language_per_segment: list
    speech_detected: bool
    error: Optional[str] = None


@dataclass
class BenchmarkEnvironment:
    faster_whisper_version: str
    torch_version: str
    cuda_available: bool
    cuda_device_name: Optional[str]
    cuda_memory_gb: Optional[float]
    cpu_name: str
    ram_gb: float
    benchmark_date: str
    ffmpeg_profile: str


def get_environment():
    """Gather benchmark environment information."""
    import platform
    try:
        import faster_whisper
        fw_version = faster_whisper.__version__
    except ImportError:
        fw_version = "NOT INSTALLED"
        print("ERROR: faster-whisper not installed. Run: pip install faster-whisper")
        sys.exit(1)

    try:
        import torch
        torch_version = torch.__version__
        cuda_available = torch.cuda.is_available()
        cuda_device_name = torch.cuda.get_device_name(0) if cuda_available else None
        cuda_memory = torch.cuda.get_device_properties(0).total_mem / (1024**3) if cuda_available else None
    except ImportError:
        torch_version = "NOT INSTALLED"
        cuda_available = False
        cuda_device_name = None
        cuda_memory = None

    cpu_name = platform.processor() or "Unknown"
    ram_gb = round(os.sysconf('SC_PAGE_SIZE') * os.sysconf('SC_PHYS_PAGES') / (1024**3), 1) if hasattr(os, 'sysconf') else 0.0

    return BenchmarkEnvironment(
        faster_whisper_version=fw_version,
        torch_version=torch_version,
        cuda_available=cuda_available,
        cuda_device_name=cuda_device_name,
        cuda_memory_gb=cuda_memory,
        cpu_name=cpu_name,
        ram_gb=ram_gb,
        benchmark_date=time.strftime('%Y-%m-%d %H:%M:%S'),
        ffmpeg_profile="16kHz mono PCM 16-bit (default)",
    )


def get_media_duration(filepath: str) -> float:
    """Get media duration using ffprobe."""
    import subprocess
    try:
        result = subprocess.run(
            ['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration',
             '-of', 'default=noprint_wrappers=1:nokey=1', filepath],
            capture_output=True, text=True, timeout=30
        )
        return float(result.stdout.strip()) if result.stdout.strip() else 0.0
    except Exception:
        return 0.0


def prepare_audio(input_path: str, output_path: str) -> bool:
    """Prepare audio: 16kHz, mono, PCM 16-bit."""
    import subprocess
    try:
        result = subprocess.run(
            ['ffmpeg', '-y', '-i', input_path,
             '-ar', '16000', '-ac', '1', '-sample_fmt', 's16',
             '-f', 'wav', output_path],
            capture_output=True, timeout=300
        )
        return result.returncode == 0
    except Exception:
        return False


def run_benchmark(model_name: str, audio_path: str, device: str, compute_type: str):
    """Run inference with a given model."""
    from faster_whisper import WhisperModel

    model = WhisperModel(model_name, device=device, compute_type=compute_type)

    start_time = time.time()
    segments, info = model.transcribe(
        audio_path,
        language=None,
        task="transcribe",
        vad_filter=True,
    )

    segment_list = []
    full_text_parts = []
    for seg in segments:
        segment_list.append({
            'segment_index': len(segment_list),
            'start_seconds': seg.start,
            'end_seconds': seg.end,
            'text': seg.text.strip(),
            'language': getattr(seg, 'language', info.language),
        })
        full_text_parts.append(seg.text.strip())

    processing_time = time.time() - start_time

    return BenchmarkResult(
        sample_id=os.path.basename(audio_path),
        sample_description="",
        sample_duration_seconds=get_media_duration(audio_path),
        model=model_name,
        device=device,
        compute_type=compute_type,
        processing_duration_seconds=round(processing_time, 3),
        real_time_factor=round(processing_time / max(get_media_duration(audio_path), 0.01), 4),
        text_length_chars=sum(len(t) for t in full_text_parts),
        segment_count=len(segment_list),
        detected_language=info.language,
        language_per_segment=list(set(s['language'] for s in segment_list)),
        speech_detected=info.language is not None,
    )


def main():
    parser = argparse.ArgumentParser(description='RTFTT Benchmark Gate: turbo vs large-v3')
    parser.add_argument('--media-dir', required=True, help='Directory containing benchmark media files')
    parser.add_argument('--device', default='auto', help='Device: auto, cpu, cuda')
    parser.add_argument('--output', default='benchmark-results.json', help='Output JSON file')
    args = parser.parse_args()

    media_dir = Path(args.media_dir)
    if not media_dir.exists():
        print(f"ERROR: Media directory not found: {media_dir}")
        sys.exit(1)

    media_files = [f for f in media_dir.iterdir() if f.suffix.lower() in SUPPORTED_EXTENSIONS]
    if not media_files:
        print(f"ERROR: No supported media files found in {media_dir}")
        sys.exit(1)

    env = get_environment()
    device = args.device
    if device == 'auto':
        device = 'cuda' if env.cuda_available else 'cpu'

    compute_type = 'float16' if device == 'cuda' else 'int8'

    results = {'environment': asdict(env), 'results': []}

    for model_name in ['turbo', 'large-v3']:
        print(f"\n=== Benchmarking model: {model_name} ===")
        for media_file in sorted(media_files):
            print(f"  Processing: {media_file.name}")

            # Prepare audio
            prepared_path = media_file.parent / f"prepared_{media_file.stem}.wav"
            if not prepare_audio(str(media_file), str(prepared_path)):
                print(f"    SKIP: FFmpeg preparation failed")
                continue

            try:
                result = run_benchmark(model_name, str(prepared_path), device, compute_type)
                result.sample_description = f"File: {media_file.name}"
                results['results'].append(asdict(result))
                print(f"    RTF: {result.real_time_factor}, Segments: {result.segment_count}, Language: {result.detected_language}")
            except Exception as e:
                results['results'].append(asdict(BenchmarkResult(
                    sample_id=media_file.name,
                    sample_description=f"File: {media_file.name}",
                    sample_duration_seconds=get_media_duration(str(media_file)),
                    model=model_name,
                    device=device,
                    compute_type=compute_type,
                    processing_duration_seconds=0,
                    real_time_factor=None,
                    text_length_chars=0,
                    segment_count=0,
                    detected_language="",
                    language_per_segment=[],
                    speech_detected=False,
                    error=str(e),
                )))
                print(f"    ERROR: {e}")

            # Cleanup prepared audio
            if prepared_path.exists():
                prepared_path.unlink()

    with open(args.output, 'w') as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    print(f"\n=== Benchmark complete. Results written to {args.output} ===")


if __name__ == '__main__':
    main()
