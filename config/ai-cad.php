<?php

// config for Tolery/AiCad
return [
    'chat_user_model' => 'App\Models\User',
    'chat_team_model' => 'App\Models\Team',
    'onshape' => [
        'secret-key' => env('ONSHAPE_SECRET_KEY'),
        'access-key' => env('ONSHAPE_ACCESS_KEY'),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret-key' => env('STRIPE_SECRET'),
        'webhook-secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'api' => [
        'base_url' => env('AI_CAD_API_URL', 'https://tolery-dfm-docker-api.cleverapps.io/api-production'), // exemple
        'key' => env('AICAD_API_KEY'),
    ],
];
