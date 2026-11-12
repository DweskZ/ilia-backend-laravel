<?php

// IS-803 · Luis Figueroa: settings of the individual module. Values come from the
// environment; there are no secrets in this file.
return [
    // Fictitious password given to the demo users created by LuisFigueroaDemoSeeder.
    'demo_password' => env('LUISFIGUEROA_DEMO_PASSWORD'),

    // Lifetime of the bearer tokens issued by POST /api/auth/token.
    'token_ttl_minutes' => (int) env('LUISFIGUEROA_TOKEN_TTL_MINUTES', 120),

    // RabbitMQ (temas 3.1 y 3.2). Credentials come from the environment only.
    'rabbitmq' => [
        'host' => env('LUISFIGUEROA_RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('LUISFIGUEROA_RABBITMQ_PORT', 5672),
        'user' => env('LUISFIGUEROA_RABBITMQ_USER'),
        'password' => env('LUISFIGUEROA_RABBITMQ_PASSWORD'),
        'vhost' => env('LUISFIGUEROA_RABBITMQ_VHOST', '/'),
    ],

    'revision' => [
        'cola' => 'ilia.revisiones',
        // Simulated cost of the automatic review (documented in docs/LuisFigueroa-tema-3-1).
        'retardo_segundos' => (float) env('LUISFIGUEROA_REVISION_DELAY_SECONDS', 3),
    ],
];
