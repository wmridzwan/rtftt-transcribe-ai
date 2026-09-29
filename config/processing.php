<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External processing kill switch (PP-T2)
    |--------------------------------------------------------------------------
    |
    | Decided: DECISION-PP-T2-KILL-SWITCH-001. When engaged, both provider
    | resolvers force the self-hosted adapters regardless of selection keys.
    | Default false. Invalid values fail closed as engaged (force
    | self-hosted) with a warning — never a boot crash. Evaluated on every
    | provider-interface resolution. After changing the env value, run the
    | documented refresh (php artisan config:clear, or config:cache where
    | used); long-lived workers pick it up on restart/recycle.
    |
    */

    'external_kill_switch' => env('RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH', false),

];
