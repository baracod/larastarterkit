<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Sneat Starter'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Response
    |--------------------------------------------------------------------------
    */

    'api_response' => [
        // 1xx: Informational responses
        '100' => 'Continue : Le serveur a reçu la requête initiale et le client doit continuer.',
        '101' => 'Switching Protocols : Le serveur accepte de changer le protocole.',

        // 2xx: Successful responses
        '200' => 'OK : La requête a été traitée avec succès.',
        '201' => 'Created : La requête a été traitée avec succès et une nouvelle ressource a été créée.',
        '202' => "Accepted : La requête a été acceptée pour traitement, mais le traitement n'est pas terminé.",
        '203' => "Non-Authoritative Information : Le serveur a renvoyé des informations qui ne proviennent pas de la source d'origine.",
        '204' => "No Content : La requête a été traitée avec succès, mais il n'y a pas de contenu à renvoyer.",
        '205' => 'Reset Content : La requête a été traitée avec succès, et le client doit réinitialiser le document affiché.',
        '206' => 'Partial Content : Le serveur renvoie une partie du contenu demandé.',

        // 3xx: Redirection messages
        '300' => 'Multiple Choices : Plusieurs options sont disponibles pour la ressource demandée.',
        '301' => 'Moved Permanently : La ressource demandée a été déplacée de façon permanente à une nouvelle URL.',
        '302' => 'Found : La ressource demandée se trouve temporairement à une autre URL.',
        '303' => 'See Other : Pour accéder à la ressource, utilisez une autre méthode HTTP.',
        '304' => "Not Modified : La ressource n'a pas été modifiée depuis la dernière requête.",
        '305' => 'Use Proxy : La ressource doit être accédée via un proxy spécifié.',
        '307' => 'Temporary Redirect : La ressource demandée se trouve temporairement à une autre URL, mais la méthode HTTP doit rester la même.',

        // 4xx: Client error responses
        '400' => 'Bad Request : La requête est mal formée ou invalide.',
        '401' => 'Unauthorized : Accès non autorisé, authentification requise.',
        '402' => 'Payment Required : Paiement requis, mais non utilisé actuellement.',
        '403' => "Forbidden : Le serveur refuse d'exécuter la requête.",
        '404' => 'Not Found : La ressource demandée est introuvable.',
        '405' => "Method Not Allowed : La méthode HTTP utilisée n'est pas autorisée pour la ressource demandée.",
        '406' => "Not Acceptable : Le serveur ne peut pas produire une réponse qui correspond aux critères d'acceptation du client.",
        '407' => 'Proxy Authentication Required : Une authentification est nécessaire via un proxy.',
        '408' => "Request Timeout : Le serveur n'a pas reçu de requête dans le temps imparti.",
        '409' => "Conflict : La requête ne peut être complétée en raison d'un conflit avec l'état actuel de la ressource.",
        '410' => "Gone : La ressource demandée n'est plus disponible et aucune adresse de redirection n'est connue.",
        '411' => "Length Required : Le serveur refuse d'accepter la requête sans un champ Content-Length valide.",
        '412' => "Precondition Failed : Une condition donnée dans l'en-tête de la requête a échoué sur le serveur.",
        '413' => 'Payload Too Large : La taille de la charge utile de la requête dépasse les limites autorisées par le serveur.',
        '414' => "URI Too Long : L'URI fournie était trop longue pour être traitée par le serveur.",
        '415' => "Unsupported Media Type : Le type de média de la requête n'est pas pris en charge par le serveur.",
        '416' => "Range Not Satisfiable : Les données demandées dans l'en-tête Range ne peuvent pas être fournies par le serveur.",
        '417' => "Expectation Failed : L'attente spécifiée dans l'en-tête Expect ne peut pas être satisfaite par le serveur.",

        // 5xx: Server error responses
        '500' => 'Internal Server Error : Une erreur interne est survenue sur le serveur.',
        '501' => 'Not Implemented : Le serveur ne prend pas en charge la fonctionnalité requise pour traiter la requête.',
        '502' => "Bad Gateway : Le serveur a reçu une réponse invalide d'un autre serveur en amont.",
        '503' => "Service Unavailable : Le service est temporairement indisponible, généralement en raison d'une surcharge ou d'une maintenance.",
        '504' => "Gateway Timeout : Le serveur n'a pas reçu de réponse à temps d'un autre serveur en amont.",
    ],

];
