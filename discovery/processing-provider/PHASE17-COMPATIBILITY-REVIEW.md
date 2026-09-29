# DISC-Q01 — Phase 1–7 compatibility gate

Research date: 2026-09-27. Any incompatibility = HPO ESCALATION REQUIRED, not discovery redesign.

- Phase 2 media/checksum/storage — PASS (FACT): `config/media.php` sha256/lowercase/64, exactly 524288000 B, 1 file, `.staging`→`media`, ffprobe path. ESCALATION sub-item: derived chunk artifact checksum/storage identity UNKNOWN — original checksum stays authoritative (INFERENCE).
- Phase 3 normalized result — PASS w/ constraint: chunk path must emit exactly NormalizedTranscript (+no-speech form); confidence/words/chunks dropped. New persisted field → ESCALATION.
- Phase 5 lifecycle — PASS w/ constraint: disjoint lifecycles preserved; translation stays machine-source-only text path; chunking lives inside transcription preparing/transcribing only (INFERENCE).
- Phase 6 revision/staleness — PASS w/ constraint (FACT): machine source immutable; revision timing never mutates machine timing; all edit kinds invalidate, precedence Structure>Timing>Text. Re-transcribe/re-chunk of edited revision out of scope — chunk output becomes machine source only. Any silent revision replacement → ESCALATION.
- Phase 7 ops/audit — CONDITIONAL PASS + ESCALATION items: FACT both queues share provider<job<retry_after (300/330/420) with boot guards. UNKNOWN → ESCALATION: per-chunk logging/metrics/audit rows, idempotency-key surfacing, long-audio timeout calibration.

No closed contract rewritten during discovery (FACT: no production file touched).
No contradiction requiring STOP found; chunk-identity and per-chunk audit rows are the two escalation candidates carried to architecture options.
