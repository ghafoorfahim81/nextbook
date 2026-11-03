<?php

/*
| The control center (the system that sells and controls NextBook): where it
| is, and the public key its licences are signed with. The ERP can check a
| licence with the public key but can never make one.
|
| A company is tied to its customer record in the control center with
| `php artisan license:connect`, which stores the token issued for it.
*/
return [
    'url' => env('CONTROL_URL'),

    // Base64 Ed25519 public key. Without it no licence is accepted.
    'public_key' => env('CONTROL_PUBLIC_KEY'),

    'timeout' => (int) env('CONTROL_TIMEOUT', 10),

    // A licence not refreshed for this long counts as stale: the control center
    // could not be reached. A stale licence is still honoured, and its end date
    // is stretched by the grace days it came with, no further.
    'stale_after_hours' => (int) env('CONTROL_STALE_AFTER_HOURS', 36),
];
