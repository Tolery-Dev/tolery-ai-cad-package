<?php

// config for Tolery/AiCad
return [
    'chat_user_model' => 'App\Models\User',
    'chat_team_model' => 'App\Models\Team',
    'onshape' => [
        'secret-key' => env('ONSHAPE_SECRET_KEY'),
        'access-key' => env('ONSHAPE_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration (AI-CAD specific)
    |--------------------------------------------------------------------------
    |
    | These are separate from the main app's Stripe keys (STRIPE_KEY, etc.)
    | to support multiple Stripe accounts (different companies).
    |
    */
    'stripe' => [
        'key' => env('AICAD_STRIPE_KEY'),
        'secret' => env('AICAD_STRIPE_SECRET'),
        'webhook_secret' => env('AICAD_STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Purchase Configuration
    |--------------------------------------------------------------------------
    */
    'file_purchase_price' => env('AICAD_FILE_PURCHASE_PRICE', 999), // Prix en centimes (9.99€ par défaut)

    'api' => [
        'base_url' => env('AI_CAD_API_URL', 'https://tolery-dfm-docker-api.cleverapps.io/api-production'), // exemple
        'key' => env('AICAD_API_KEY'),
        'url' => env('AI_CAD_API_URL', 'https://tolery-dfm-docker-api.cleverapps.io/api-production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the ToleryCAD admin panel.
    |
    */
    'admin' => [
        'enabled' => env('AICAD_ADMIN_ENABLED', true),
        'prefix' => env('AICAD_ADMIN_PREFIX', 'admin/tolerycad'),
        'middleware' => ['web', 'auth:sanctum', 'verified', 'has_role:admin', 'active_user'],
        'nav_items' => [
            [
                'name' => 'ToleryCad',
                'route' => 'ai-cad.admin.dashboard',
                'icon' => 'cube',
                'children' => [
                    ['name' => 'Dashboard', 'route' => 'ai-cad.admin.dashboard'],
                    ['name' => 'Conversations', 'route' => 'ai-cad.admin.chats.index'],
                    ['name' => 'Achats', 'route' => 'ai-cad.admin.purchases.index'],
                    ['name' => 'Téléchargements', 'route' => 'ai-cad.admin.downloads.index'],
                    ['name' => 'Prompts', 'route' => 'ai-cad.admin.prompts.index'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Predefined Prompts Configuration
    |--------------------------------------------------------------------------
    |
    | Source for predefined prompts:
    | - 'database': Use the predefined_prompts table (recommended for admin management)
    | - 'config': Use the predefined_prompts array below (legacy)
    |
    */
    'prompts_source' => env('AICAD_PROMPTS_SOURCE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Predefined Prompts (UI suggestions) - Legacy Config
    |--------------------------------------------------------------------------
    |
    | Example prompts shown in the UI to help users get started.
    | Only used when prompts_source = 'config'
    |
    */
    'predefined_prompts' => [
        'Plaque' => 'Je veux une pièce de dimension 500x 250 mm et une épaisseur de 2mm, avec un perçage au centre de diamètre 50mm',
        'Platine taraudée' => 'Je veux un fichier pour une platine de 200mm de longueur, 200mm en largeur, épaisseur 5mm. Il faut 4 perçages taraudés M6 dans chaques coins situés à 25mm des bords. Peux tu ajouter des rayons de 15mm dans chaque angle',
        'Support en L' => 'Créer un support en forme de L, avec une base de 100 mm, une hauteur de 60 mm, une largeur de 30 mm, d épaisseur 2 mm, avec un pli à 90° et un rayon de pliage intérieur de 2 mm et exterieur de 4mm, comprenant deux trous de 6 mm de diamètre sur la base espacés de 70 mm, centrés en largeur, ainsi qu un trou de 8 mm de diamètre centré sur la partie de 60mm. Ajouter des rayons de 5mm dans chaque coins',
        'Tube rectangulaire' => 'Je souhaite créer un fichier CAO pour un tube rectangulaire de 1400 mm de long, avec une section de 60 x 30 mm, une épaisseur de 2 mm, des coupes droites à chaque extrémité, un rayon intérieur égal à l épaisseur (2 mm) et un rayon extérieur égal à deux fois l épaisseur (4 mm)',
    ],
];
