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
        'url' => env('AI_CAD_API_URL', 'https://tolery-dfm-docker-api.cleverapps.io/api-production'),
    ],
    'cache' => [
        'enabled' => env('AI_CAD_CACHE_ENABLED', true),
        'simulation_duration_ms' => env('AI_CAD_CACHE_SIMULATION_MS', 10000), // 10 seconds
        'retention_days' => env('AI_CAD_CACHE_RETENTION_DAYS', 30),
        'predefined_prompts' => [
            '📄 Plaque' => 'Je veux une pièce de dimension 500x 250 mm et une épaisseur de 2mm, avec un perçage au centre de diamètre 50mm',
            '⚙️ Platine taraudée' => 'Je veux un fichier pour une platine de 200mm de longueur, 200mm en largeur, épaisseur 5mm. Il faut 4 perçages taraudés M6 dans chaques coins situés à 25mm des bords. Peux tu ajouter des rayons de 15mm dans chaque angle',
            '📐 Support en L' => 'Créer un support en forme de L, avec une base de 100 mm, une hauteur de 60 mm, une largeur de 30 mm, d épaisseur 2 mm, avec un pli à 90° et un rayon de pliage intérieur de 2 mm et exterieur de 4mm, comprenant deux trous de 6 mm de diamètre sur la base espacés de 70 mm, centrés en largeur, ainsi qu un trou de 8 mm de diamètre centré sur la partie de 60mm. Ajouter des rayons de 5mm dans chaque coins',
            '🔲 Tube rectangulaire' => 'Je souhaite créer un fichier CAO pour un tube rectangulaire de 1400 mm de long, avec une section de 60 x 30 mm, une épaisseur de 2 mm, des coupes droites à chaque extrémité, un rayon intérieur égal à l épaisseur (2 mm) et un rayon extérieur égal à deux fois l épaisseur (4 mm)',
        ],
    ],
];
