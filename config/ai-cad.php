<?php

// config for Tolery/AiCad
return [
    'api-url' => env('AI_CAD_API_URL', 'https://preprod-ai-cad.cleverapps.io'),
    'onshape' => [

        'secret-key' => env('ONSHAPE_SECRET_KEY'),
        'access-key' => env('ONSHAPE_ACCESS_KEY'),
    ],
];
