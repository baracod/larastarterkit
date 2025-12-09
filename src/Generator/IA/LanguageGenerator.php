<?php

/**
 *  Fichier  : app/Generator/IA/LanguageGenerator.php
 *  Namespace: Baracod\Larastarterkit\Generator\IA
 *
 *  Génère un JSON bilingue (fr / en) via l’API Google Gemma/Gemini.
 *  — Modèle par défaut : gemma-3-27b-it (pas de JSON mode natif)
 *  — Clé API          : .env ➜ GEMINI_API_KEY
 *  — Mode verbose     : TRUE pour afficher les grandes étapes
 */

namespace Baracod\Larastarterkit\Generator\IA;

use Baracod\Larastarterkit\Generator\IA\Exceptions\LanguageGeneratorException;
use Illuminate\Support\Facades\Http;

/* -------------------------------------------------------------------------- */
/*  Service principal */
/* -------------------------------------------------------------------------- */

class LanguageGenerator
{
    /* ====== Configuration par défaut ====== */
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const DEFAULT_MODEL = 'gemma-3-27b-it';

    private const DEFAULT_GENCFG = [
        // Pas de responseMimeType avec Gemma
        'temperature' => 0.2,
        'maxOutputTokens' => 1024,
    ];

    /* ====== Attributs ====== */
    private string $apiKey;

    private string $modelId;

    private array $genCfg;

    private bool $verbose = false;   // ← nouveau

    /* ====== Constructeur ====== */
    public function __construct(
        ?string $apiKey = null,
        ?string $modelId = null,
        array $genCfg = self::DEFAULT_GENCFG,
        bool $verbose = false,      // ← nouveau
    ) {
        $this->apiKey = $apiKey ?? env('GEMINI_API_KEY');
        $this->modelId = $modelId ?? self::DEFAULT_MODEL;
        $this->genCfg = array_merge(self::DEFAULT_GENCFG, $genCfg);
        $this->verbose = $verbose;

        if (empty($this->apiKey)) {
            throw new LanguageGeneratorException('Variable d’environnement GEMINI_API_KEY manquante.');
        }
    }

    /* ====== Méthode publique principale ====== */
    public function generateBilingualJson(
        string $entity,
        string $module,
        array $fields,
        bool $asArray = false,
    ): array|string {
        $this->step("📌 Génération JSON bilingue pour {$entity} ({$module})");

        $prompt = $this->buildPrompt($entity, $module, $fields);
        $contents = [[
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ]];

        $url = $this->endpoint('generateContent');
        $payload = [
            'contents' => $contents,
            'generationConfig' => $this->genCfg,
        ];

        $this->step('🔗 Appel API Gemma…');

        try {
            $response = Http::timeout(300)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->failed()) {
                throw new LanguageGeneratorException(
                    "Erreur API ({$response->status()}) : {$response->body()}"
                );
            }

            $this->step('✅ Réponse reçue. Extraction du JSON…');

            $cleanJson = $this->extractJsonFromGeminiResponse($response->body());

            $this->step('🎉 JSON généré avec succès.');

            return $asArray
                ? json_decode($cleanJson, true, 512, JSON_THROW_ON_ERROR)
                : $cleanJson;
        } catch (\Throwable $e) {
            $this->step('❌ Échec : '.$e->getMessage());
            throw new LanguageGeneratorException(
                'Échec lors de la génération du JSON : '.$e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /* ====== Helpers privés ====== */

    /** Log minimal lorsque $verbose = true */
    private function step(string $label): void
    {
        if (! $this->verbose) {
            return;
        }

        if (app()->runningInConsole()) {
            echo "[Gemma] $label\n";
        } else {
            logger()->info("[Gemma] $label");
        }
    }

    private function buildPrompt(string $entity, string $module, array $fields): string
    {
        $fieldList = '"'.implode('","', $fields).'"';

        return <<<PROMPT
            Tu es un assistant IA spécialisé dans l’UX multilingue.
            Génère un JSON bilingue (fr/en) pour l’entité "{$entity}" (module "{$module}").

            Contraintes :
            • Les clés JSON doivent être en camelCase.
            • Ne renvoie que le JSON (aucun texte autour).
            • Structure attendue :
                {
                "fr": {
                    "menuTitle": "...",
                    "menuDescription": "...",
                    "title": "...",
                    "titlePlural": "...",
                    "field": {
                    <cléChamp1>: "...",
                    <cléChamp2>: "..."
                    }
                },
                "en": { ... identique ... }
                }

            Entoure le résultat entre ```json et ``` sans ajouter de texte.
            Liste des champs : [{$fieldList}]
        PROMPT;
    }

    private function endpoint(string $method): string
    {
        return sprintf('%s/%s:%s?key=%s', self::BASE_URL, $this->modelId, $method, $this->apiKey);
    }

    private function extractJsonFromGeminiResponse(string $body): string
    {
        $wrapped = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $jsonString = $wrapped['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! $jsonString) {
            throw new LanguageGeneratorException('Réponse inattendue : JSON manquant.');
        }

        // Nettoyage ```json ... ```
        $jsonString = trim($jsonString);
        if (str_starts_with($jsonString, '```')) {
            $jsonString = preg_replace('/^```(?:json)?\s*/', '', $jsonString);
            $jsonString = preg_replace('/\s*```$/', '', $jsonString);
        }

        // Validation et ré-encodage propre
        $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        $decoded = $this->forceKeysToCamelCase($decoded); // forcer les clé en camelcase

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convertit récursivement toutes les clés d’un tableau en camelCase,
     * sauf si elles sont déjà en camelCase ou si ce sont des entiers.
     */
    private function forceKeysToCamelCase(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $newKey = $key;
            } elseif ($this->isCamelCase($key)) {
                $newKey = $key;
            } else {
                $newKey = $this->toCamelCase($key);
            }

            $result[$newKey] = is_array($value)
                ? $this->forceKeysToCamelCase($value)
                : $value;
        }

        return $result;
    }

    /**
     * Détecte si une chaîne est déjà en camelCase.
     * Exemples valides : "schoolName", "createdAt"
     */
    private function isCamelCase(string $key): bool
    {
        return preg_match('/^[a-z]+(?:[A-Z][a-z0-9]+)*$/', $key);
    }

    /**
     * Convertit une chaîne snake_case ou kebab-case en camelCase.
     */
    private function toCamelCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', strtolower($string));
        $string = ucwords($string); // ex: school_name => SchoolName
        $string = str_replace(' ', '', $string);

        return lcfirst($string);    // SchoolName => schoolName
    }
}
