"""Guard test for the canonical Phase 5 translation worker runtime (ADR-024).

The declared pins in ``worker/requirements.txt`` are the single canonical
translation runtime. The P5-008 real gate must run against exactly this set, so
a drift here must fail the suite rather than silently diverge from the gated
runtime.
"""

from pathlib import Path

REQUIREMENTS = Path(__file__).resolve().parent.parent / "requirements.txt"

# ADR-024 / DECISION-P5-008-CORRECTIVE-001 canonical pins.
CANONICAL_PINS = {
    "transformers": "5.17.0",
    "torch": "2.14.0",
    "sentencepiece": "0.2.2",
}


def _declared_pins() -> dict[str, str]:
    pins: dict[str, str] = {}

    for raw_line in REQUIREMENTS.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if not line or line.startswith("#") or "==" not in line:
            continue

        name, version = line.split("==", 1)
        pins[name.strip()] = version.strip()

    return pins


def test_canonical_translation_runtime_is_exactly_pinned():
    pins = _declared_pins()

    for name, version in CANONICAL_PINS.items():
        assert pins.get(name) == version, (
            f"{name} must be pinned to the canonical ADR-024 version {version}; "
            f"found {pins.get(name)!r} in worker/requirements.txt."
        )


def test_no_generic_range_for_translation_runtime():
    text = REQUIREMENTS.read_text(encoding="utf-8")

    for name in CANONICAL_PINS:
        assert f"{name}>=" not in text, (
            f"{name} must not be declared as a generic range; the canonical "
            "runtime is exact-pinned (ADR-024)."
        )
