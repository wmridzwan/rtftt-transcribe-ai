<?php

namespace App\Retention;

/**
 * Raised when a purge candidate disappears or is re-owned mid-run
 * (concurrent user deletion). The purge backs off with a `skipped`
 * audit — it never resurrects, re-marks, or errors the run.
 */
class StalePurgeCandidate extends \RuntimeException {}
