# Retention Policy — User-Facing Disclosure (P7-011, D7-06)

## What is retained, and for how long

- **Your media and transcripts are kept for 30 days after successful
  processing.** The 30-day clock starts when processing completes
  successfully — not when you upload.
- **Incomplete or failed processing is never automatically deleted.**
  If processing did not finish, your files stay until you delete them
  yourself (or processing completes and the 30-day clock starts).
- **Temporary upload artifacts** (partial/interrupted uploads) are
  cleaned up 24 hours after upload.
- **Security-quarantined files** are never auto-deleted; they require
  explicit operator review.
- **Backups** follow their own protected schedule and are never
  removed by retention cleanup.

## What happens after 30 days

- The **original media file is permanently deleted** (it can no longer
  be played back or downloaded).
- **Your transcript history stays available**: transcripts, revision
  history, translations, and text exports (TXT/SRT/VTT/DOCX) continue
  to work — they are marked as "source purged" so it is always clear
  the original recording is gone.
- Every deletion is recorded in an audit ledger; deletions are never
  silent.

## What you control

- **Export any time**: download your transcript or media before the
  30-day window ends and you keep your own copy.
- **Delete any time**: deleting a media item yourself (with the
  explicit confirmation shown) removes it and its transcripts
  immediately — you never have to wait for automatic cleanup.
- Playback or download of a purged recording reports
  "purged under the 30-day retention policy" (HTTP 410) instead of a
  generic missing-file error.
