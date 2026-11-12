<?php

// IS-803 · Luis Figueroa: settings of the individual module. Values come from the
// environment; there are no secrets in this file.
return [
    // Fictitious password given to the demo users created by LuisFigueroaDemoSeeder.
    'demo_password' => env('LUISFIGUEROA_DEMO_PASSWORD'),

    // Lifetime of the bearer tokens issued by POST /api/auth/token.
    'token_ttl_minutes' => (int) env('LUISFIGUEROA_TOKEN_TTL_MINUTES', 120),
];
