<?php

// config for Tolery/AiCad
return [
    'api-url' => env('AI_CAD_API_URL', 'https://preprod-ai-cad.cleverapps.io'),
    'onshape' => [

        'secret-key' => env('ONSHAPE_SECRET_KEY'),
        'access-key' => env('ONSHAPE_ACCESS_KEY'),
    ],
    'usage-limiter' => [
        'relationship' => 'limits',
        'tables' => [
            'limits' => 'subscription_products_limits',
            'model_has_limits' => 'subscription_has_limits',
        ],
        'columns' => [
            'limit_pivot_key' => 'limit_id',
        ],
        'cache' => [
            'expiration_time' => \DateInterval::createFromDateString('24 hours'),
            'key' => 'nabilhassen.limits.cache',
            'store' => 'default',
        ],
    ],
];
