"""Tests for script-integrity heuristic analysis.

These tests specifically cover the script classes the original detector
missed (post-escalation review, BLOCKER-1): Hebrew, Cyrillic, Korean,
Arabic, Gurmukhi, plus empty-transcript handling.
"""

from scripts.benchmark.script_integrity import (
    analyze_transcript,
    classify_char,
    expected_scripts_for,
)


class TestClassifyChar:
    def test_tamil(self):
        assert classify_char("\u0B95") == "Tamil"

    def test_latin(self):
        assert classify_char("a") == "Latin"

    def test_cjk(self):
        assert classify_char("\u4E00") == "CJK"

    def test_hebrew(self):
        assert classify_char("\u05D0") == "Hebrew"

    def test_cyrillic(self):
        assert classify_char("\u0410") == "Cyrillic"

    def test_korean(self):
        assert classify_char("\uAC00") == "Korean"

    def test_arabic(self):
        assert classify_char("\u0627") == "Arabic"

    def test_gurmukhi(self):
        assert classify_char("\u0A17") == "Gurmukhi"

    def test_greek(self):
        assert classify_char("\u03B1") == "Greek"


class TestExpectedScriptsFor:
    def test_tamil_alias(self):
        assert expected_scripts_for("ta_in_1") == {"Tamil", "Latin"}

    def test_malay_alias(self):
        assert expected_scripts_for("ms_my_1") == {"Latin"}

    def test_english_alias(self):
        assert expected_scripts_for("en_us_1") == {"Latin"}

    def test_chinese_alias(self):
        assert expected_scripts_for("cmn_hans_cn_1") == {"CJK", "Latin"}

    def test_mixed_alias(self):
        assert expected_scripts_for("MIX-01") == {"Latin", "Tamil", "CJK"}


class TestAnalyzeTranscript:
    def test_clean_tamil_passes(self):
        result = analyze_transcript("இந்த விதிகள் திருத்தப்படுதல்", {"Tamil", "Latin"})
        assert result["clean"] is True
        assert result["empty"] is False

    def test_latin_tolerated_in_tamil(self):
        # Names/acronyms/numbers in Latin are tolerated
        result = analyze_transcript("இந்த Tamil 123", {"Tamil", "Latin"})
        assert result["clean"] is True

    def test_hebrew_detected(self):
        """The original detector missed Hebrew."""
        result = analyze_transcript("இந்த \u05D0\u05D1\u05D2", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Hebrew" in result["unexpected_scripts"]

    def test_cyrillic_detected(self):
        """The original detector missed Cyrillic."""
        result = analyze_transcript("இந்த \u0410\u0411\u0412", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Cyrillic" in result["unexpected_scripts"]

    def test_korean_detected(self):
        """The original detector missed Korean (Hangul)."""
        result = analyze_transcript("இந்த \uAC00\uAC01", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Korean" in result["unexpected_scripts"]

    def test_arabic_detected(self):
        """The original detector missed Arabic."""
        result = analyze_transcript("இந்த \u0627\u0628\u062A", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Arabic" in result["unexpected_scripts"]

    def test_gurmukhi_detected(self):
        """Gurmukhi contamination must be detected (large-v3 ta_in_15)."""
        result = analyze_transcript("ਗਨਡਤਿਲ ਇਰਿਂਦ", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Gurmukhi" in result["unexpected_scripts"]

    def test_greek_detected(self):
        result = analyze_transcript("இந்த \u03B1\u03B2\u03B3", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "Greek" in result["unexpected_scripts"]

    def test_empty_transcript_flagged_not_clean(self):
        """The original detector auto-passed empty transcripts as clean."""
        result = analyze_transcript("", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert result["empty"] is True
        assert any("Empty" in f for f in result["flags"])

    def test_whitespace_only_flagged_not_clean(self):
        result = analyze_transcript("   \n\t  ", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert result["empty"] is True

    def test_chinese_cjk_passes_in_zh_context(self):
        result = analyze_transcript("报告警告称", {"CJK", "Latin"})
        assert result["clean"] is True

    def test_cjk_flagged_in_tamil_context(self):
        result = analyze_transcript("இந்த 报告", {"Tamil", "Latin"})
        assert result["clean"] is False
        assert "CJK" in result["unexpected_scripts"]

    def test_legitimate_borrowing_not_auto_failed(self):
        """Numbers/punctuation must not create false positives."""
        result = analyze_transcript("இந்த 123, 456. 789", {"Tamil", "Latin"})
        assert result["clean"] is True
