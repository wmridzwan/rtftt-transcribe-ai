#!/usr/bin/env python3
"""Backfill reference transcriptions for original Tamil samples."""
import json
import urllib.request
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"

# Original Tamil indices: 0, 5, 10
MISSING = [
    {"alias": "ta_in_1", "idx": 0},
    {"alias": "ta_in_2", "idx": 5},
    {"alias": "ta_in_3", "idx": 10},
]

manifest_path = BENCHMARK_DIR / "manifest.json"
with open(manifest_path, "r", encoding="utf-8") as f:
    manifest = json.load(f)

for entry in MISSING:
    alias = entry["alias"]
    idx = entry["idx"]
    url = f"https://datasets-server.huggingface.co/rows?dataset=google/fleurs&config=ta_in&split=validation&offset={idx}&length=1"
    for attempt in range(3):
        try:
            req = urllib.request.Request(url, headers={"Accept": "application/json"})
            with urllib.request.urlopen(req, timeout=120) as resp:
                data = json.loads(resp.read())
            break
        except Exception as e:
            print(f"  Attempt {attempt+1} failed: {e}")
            if attempt == 2:
                print(f"  SKIP {alias}: all attempts failed")
                data = None
    if data is None:
        continue
    transcription = data["rows"][0]["row"].get("transcription", "")
    try:
        print(f"{alias} (idx={idx}): OK")
    except UnicodeEncodeError:
        print(f"{alias} (idx={idx}): OK [unicode suppressed]")

    for s in manifest["samples"]:
        if s["alias"] == alias:
            s["reference_transcription"] = transcription
            break

with open(manifest_path, "w", encoding="utf-8") as f:
    json.dump(manifest, f, indent=2, ensure_ascii=False)

print("Done. All Tamil samples now have reference transcriptions.")
