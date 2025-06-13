<?php

// config for Tolery/AiCad
return [
    'api-url' => env('AI_CAD_API_URL', 'https://tolery-dfm-docker-api.cleverapps.io/api-production'),
    'chat_user_model' => 'App\Models\User',
    'chat_team_model' => 'App\Models\Team',
    'onshape' => [
        'secret-key' => env('ONSHAPE_SECRET_KEY'),
        'access-key' => env('ONSHAPE_ACCESS_KEY'),
    ],
];
