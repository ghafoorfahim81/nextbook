<?php

/**
 * The platform panel (phase 10) manages every customer's package, invoices and
 * payments from inside this app. It is a stop-gap until that moves to its own
 * system, so it is off unless PLATFORM_PANEL_ENABLED is set. What each
 * customer sees of their own subscription (/subscription) does not depend on it.
 */
return [
    'panel_enabled' => (bool) env('PLATFORM_PANEL_ENABLED', false),
];
