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

    // Lets the control center create a company here (POST /api/control/provision),
    // with an administrator able to act in it. Off unless this is set: the
    // route answers 404 and creates nothing. A request is accepted only when it
    // is signed by the control center's private key (checked with the public
    // key above), is no older than this many seconds, and has not been seen
    // before. In production it must also arrive over HTTPS.
    'provisioning' => (bool) env('CONTROL_PROVISIONING', false),
    'provisioning_window_seconds' => (int) env('CONTROL_PROVISIONING_WINDOW', 300),
];
