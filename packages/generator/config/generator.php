<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistance IA du générateur (Laravel AI SDK)
    |--------------------------------------------------------------------------
    |
    | L'IA n'est jamais indispensable : chaque fonctionnalité possède un
    | équivalent déterministe utilisé lorsque l'IA est désactivée, qu'aucun
    | fournisseur n'est configuré ou qu'un appel échoue. Les fournisseurs et
    | leurs clés se configurent dans config/ai.php (laravel/ai).
    |
    */

    'ai' => [
        'enabled' => (bool) env('GENERATOR_AI_ENABLED', true),

        // Fournisseur de config/ai.php ("openai", "anthropic", "gemini", "ollama"…). Null = défaut de laravel/ai.
        'provider' => env('GENERATOR_AI_PROVIDER'),

        // Modèle précis ; null = modèle par défaut du fournisseur.
        'model' => env('GENERATOR_AI_MODEL'),

        'timeout' => (int) env('GENERATOR_AI_TIMEOUT', 90),

        // Fonctionnalités activables individuellement.
        'features' => [
            'translations' => (bool) env('GENERATOR_AI_TRANSLATIONS', true),
            'design' => (bool) env('GENERATOR_AI_DESIGN', true),
            'validation' => (bool) env('GENERATOR_AI_VALIDATION', true),
            'fake_data' => (bool) env('GENERATOR_AI_FAKE_DATA', true),
        ],
    ],

    // Langues des fichiers i18n générés pour le frontend.
    'locales' => ['fr', 'en'],

    // Colonnes techniques ignorées dans les formulaires et les imports de table.
    'technical_columns' => ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by_id', 'updated_by_id', 'deleted_by_id'],

    // Déclare automatiquement les espaces de noms d'un module dans composer.json (puis composer dump-autoload).
    'register_autoload' => (bool) env('GENERATOR_REGISTER_AUTOLOAD', true),

    // Formate les fichiers PHP générés avec Pint (vendor/bin/pint).
    'format_php' => (bool) env('GENERATOR_FORMAT_PHP', true),

    // Corrige le style des fichiers TypeScript/Vue générés avec ESLint --fix.
    'format_frontend' => (bool) env('GENERATOR_FORMAT_FRONTEND', true),

    // Nombre d'enregistrements créés par le seeder de démonstration généré.
    'seed_count' => 10,
];
