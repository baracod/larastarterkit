<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class ApiDocGen
{
    public function __construct(
        private readonly string $outPath = './swagger.json',
        private readonly string $authType = 'bearer',
        private readonly string $apiKeyHeader = 'X-Api-Key',
        private readonly bool   $secureByDefault = true,
        private readonly string $apiBase = '/api',
        private readonly string $apiVersion = 'v1',
        private readonly ?string $forcedServerUrl = null, // optionnel pour override
    ) {}

    /**
     * Build OpenAPI from a Module JSON definition (array decoded)
     */
    public function build(array $def): void
    {
        $doc = $this->baseDoc(
            title: '📘 API ' . $def['module'],
            description: "Documentation générée automatiquement à partir des définitions du module.",
        );

        // Tag par modèle (contrôleur)
        foreach ($def['models'] as $modelKey => $m) {
            if (!Arr::get($m, 'backend.hasController') || !Arr::get($m, 'backend.hasRoute')) {
                continue;
            }

            $tagName = Arr::get($m, 'name', ucfirst($modelKey));
            $doc['tags'][] = [
                'name' => $tagName,
                'description' => "Endpoints du modèle {$tagName}",
            ];

            // Schéma à partir de fillable
            $schemaName = Arr::get($m, 'name', 'Model');
            $doc['components']['schemas'][$schemaName] = $this->schemaFromFillable(
                Arr::get($m, 'fillable', [])
            );

            // Générer les 5 routes RESTful sur apiRoute
            $apiRoute = trim(Arr::get($m, 'backend.apiRoute', ''), '/'); // ex: "api/blog/blog-authors"
            if ($apiRoute === '') {
                continue;
            }

            $resourcePath  = $this->buildVersionedPath($apiRoute);;  // /api/blog/blog-authors
            $resourceById  = $resourcePath . '/{id}';               // /api/blog/blog-authors/{id}

            $doc['paths'][$resourcePath]['get']    = $this->withSecurity($this->opIndex($tagName, $schemaName));
            $doc['paths'][$resourcePath]['post']   = $this->withSecurity($this->opStore($tagName, $schemaName));
            $doc['paths'][$resourceById]['get']    = $this->withSecurity($this->opShow($tagName, $schemaName));
            $doc['paths'][$resourceById]['put']    = $this->withSecurity($this->opUpdate($tagName, $schemaName));
            $doc['paths'][$resourceById]['patch']  = $this->withSecurity($this->opUpdate($tagName, $schemaName));
            $doc['paths'][$resourceById]['delete'] = $this->withSecurity($this->opDestroy($tagName));
        }

        // Écriture
        $out = $this->outPath !== '' ? $this->outPath : base_path('docs/openapi.json');
        File::ensureDirectoryExists(dirname($out));
        File::put($out, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // ---------- Builders OpenAPI ----------

    private function baseDoc(string $title, string $description): array
    {
        $raw = rtrim(config('app.url', 'http://localhost'), '/');
        // ✨ serveur versionné
        $serverUrl = $raw; //. $this->normalizedPrefix(); // ex: https://local.akhademie-v1.com/api/v1

        $doc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $title,
                'description' => $description,
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => $serverUrl, 'description' => 'Serveur principal'],
            ],
            'tags' => [],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => $this->securitySchemes(),
            ],
        ];

        // Sécurité globale (toutes les opérations)
        if ($this->secureByDefault) {
            $doc['security'] = [$this->securityRequirement()];
        }

        return $doc;
    }

    // ✨ Helper: /api/v1 (toujours propre, sans double slash)
    private function normalizedPrefix(): string
    {
        $base = '/' . ltrim($this->apiBase, '/');       // /api
        $ver  = '/' . ltrim($this->apiVersion, '/');    // /v1
        return rtrim($base . $ver, '/');                // /api/v1
    }

    // ✨ Helper: construit le chemin complet à partir de backend.apiRoute
    private function buildVersionedPath(string $apiRoute): string
    {
        $route = '/' . ltrim($apiRoute, '/'); // ex: /api/blog/blog-authors OU /blog/blog-authors

        // Si l’apiRoute contient déjà /api ou /v1, on les retire pour éviter les doublons
        $route = preg_replace('#^/api(/v\d+)?#', '', $route); // enlève /api et éventuellement /api/vX au début

        // Ajoute le préfixe versionné
        return $this->normalizedPrefix() . $route; // ex: /api/v1/blog/blog-authors
    }

    private function securitySchemes(): array
    {
        if ($this->authType === 'apiKey') {
            // X-Api-Key: <token>
            return [
                'ApiKeyAuth' => [
                    'type' => 'apiKey',
                    'in'   => 'header',
                    'name' => $this->apiKeyHeader,
                    'description' => "Fournissez votre clé d'API dans l'en-tête {$this->apiKeyHeader}.",
                ],
            ];
        }

        // Par défaut: Authorization: Bearer <token> (Sanctum/Passport/JWT)
        return [
            'BearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => "Utilisez un jeton Bearer dans l'en-tête Authorization: Bearer <token>.",
            ],
        ];
    }

    private function securityRequirement(): array
    {
        return ($this->authType === 'apiKey')
            ? ['ApiKeyAuth' => []]
            : ['BearerAuth' => []];
    }

    private function withSecurity(array $operation): array
    {
        // Si sécurité globale, l’opération héritera. Mais on garde la ligne suivante
        // pour expliciter l’exigence au niveau de chaque opération (utile si tu désactives le global).
        if ($this->secureByDefault) {
            // Rien à faire (déjà au global). Décommente si tu veux aussi au niveau opération :
            // $operation['security'] = [$this->securityRequirement()];
            return $operation;
        }

        // Pas de sécurité globale: on l'ajoute par opération
        $operation['security'] = [$this->securityRequirement()];
        return $operation;
    }

    private function schemaFromFillable(array $fillable): array
    {
        $props = [];
        $required = [];

        foreach ($fillable as $f) {
            $name = (string) Arr::get($f, 'name', '');
            if ($name === '') continue;

            $type = $this->mapSqlToJsonType((string) Arr::get($f, 'type', 'string'));

            // Gestion simple des arrays/json
            if ($type === 'array') {
                $props[$name] = [
                    'type' => 'array',
                    'items' => ['type' => 'string'] // ajuste si tu connais la structure
                ];
            } else {
                $props[$name] = ['type' => $type];
            }

            if (!Arr::get($f, 'nullable', false)) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $props,
        ];
        if (!empty($required)) {
            $schema['required'] = array_values(array_unique($required));
        }
        return $schema;
    }

    private function mapSqlToJsonType(string $sql): string
    {
        $s = strtolower($sql);
        return match (true) {
            str_contains($s, 'int')      => 'integer',
            str_contains($s, 'decimal'),
            str_contains($s, 'float'),
            str_contains($s, 'double'),
            str_contains($s, 'numeric')  => 'number',
            str_contains($s, 'bool'),
            str_contains($s, 'tinyint(1)') => 'boolean',
            str_contains($s, 'json'),
            str_contains($s, 'array')    => 'array',
            default                      => 'string',
        };
    }

    // ---------- Operations (index, store, show, update, destroy) ----------

    private function opIndex(string $tag, string $schema): array
    {
        return [
            'tags' => [$tag],
            'summary' => "Lister {$tag}",
            'parameters' => [
                [
                    'name' => 'page',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1],
                    'description' => 'Numéro de page (pagination).'
                ],
                [
                    'name' => 'per_page',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    'description' => 'Taille de page (pagination).'
                ],
                [
                    'name' => 'q',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                    'description' => 'Recherche texte.'
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Liste paginée',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => ['$ref' => "#/components/schemas/{$schema}"]
                                    ],
                                    'meta' => ['type' => 'object'],
                                ]
                            ]
                        ]
                    ]
                ],
                '401' => ['description' => 'Non authentifié'],
            ],
        ];
    }

    private function opStore(string $tag, string $schema): array
    {
        return [
            'tags' => [$tag],
            'summary' => "Créer {$tag}",
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$schema}"]
                    ]
                ]
            ],
            'responses' => [
                '201' => [
                    'description' => 'Créé',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schema}"]
                        ]
                    ]
                ],
                '400' => ['description' => 'Erreur de validation'],
                '401' => ['description' => 'Non authentifié'],
            ],
        ];
    }

    private function pathIdParam(): array
    {
        return [[
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer', 'minimum' => 1],
            'description' => 'Identifiant de la ressource',
        ]];
    }

    private function opShow(string $tag, string $schema): array
    {
        return [
            'tags' => [$tag],
            'summary' => "Voir {$tag}",
            'parameters' => $this->pathIdParam(),
            'responses' => [
                '200' => [
                    'description' => 'Détail',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schema}"]
                        ]
                    ]
                ],
                '401' => ['description' => 'Non authentifié'],
                '404' => ['description' => 'Introuvable'],
            ],
        ];
    }

    private function opUpdate(string $tag, string $schema): array
    {
        return [
            'tags' => [$tag],
            'summary' => "Mettre à jour {$tag}",
            'parameters' => $this->pathIdParam(),
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$schema}"]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Mis à jour',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schema}"]
                        ]
                    ]
                ],
                '400' => ['description' => 'Erreur de validation'],
                '401' => ['description' => 'Non authentifié'],
                '404' => ['description' => 'Introuvable'],
            ],
        ];
    }

    private function opDestroy(string $tag): array
    {
        return [
            'tags' => [$tag],
            'summary' => "Supprimer {$tag}",
            'parameters' => $this->pathIdParam(),
            'responses' => [
                '204' => ['description' => 'Supprimé (pas de contenu)'],
                '401' => ['description' => 'Non authentifié'],
                '404' => ['description' => 'Introuvable'],
            ],
        ];
    }
}
