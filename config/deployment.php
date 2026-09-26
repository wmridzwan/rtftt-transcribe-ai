<?php

return [

    /*
     * Minimum free bytes required on the application storage volume for a
     * production deployment (P7-001 capacity signal; D7-03 local-disk
     * posture). Defaults to 1 GiB. Override per host via
     * RTFTT_MIN_FREE_BYTES.
     */
    'min_free_bytes' => (int) env('RTFTT_MIN_FREE_BYTES', 1_073_741_824),

];
