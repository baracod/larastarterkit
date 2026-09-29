# Générateur CRUD — Architecture

Guide de maintenance. Pour l'utilisation, voir [USAGE.md](USAGE.md).

## Flux

```
larastarterkit:definition ─┬─ --fields      → Ai\Fallback\FieldSyntax
                     ├─ --describe    → Ai\Assistant::designEntity (EntityDesignAgent, sinon FieldSyntax)
                     └─ --from-table  → Support\TableImporter
                              ↓
                     Support\DefinitionBuilder → Modules/{Module}/module.json (RelationResolver pour les belongsTo)
                              ↓
larastarterkit:crud / menu → GenerationOrchestrator ── ordre GenerationMode::order()
                         ├─ Backend\Database\MigrationGen
                         ├─ Backend\Model\ModelGen
                         ├─ Backend\Http\RequestGen      (Assistant::validationRules)
                         ├─ Backend\Http\ControllerGen
                         ├─ Backend\Http\RouteGen
                         ├─ Backend\Database\PermissionSeederGen
                         ├─ Backend\Database\FactoryGen  (Assistant::fakeFormatters)
                         ├─ Backend\Testing\ApiTestGen
                         └─ Frontend\TypeScriptGeneratorFromJson (types, api, index, form, menu, i18n ← Assistant::translations)
                              ↓
                     formatage Pint (PHP) + ESLint --fix (TS/Vue), puis Console\ReportPrinter
```

## Définitions

- `DefinitionFile\FieldDefinition` : `name`, `type` (`Enums\FieldType`), `defaultValue`, `customizedType`, `nullable`, `unique`, `length`, `enumValues`, `label`.
  Compatibilité : sans `nullable`, un `defaultValue` nul signifie « facultatif » (ancien format). Les attributs non renseignés ne sont pas écrits.
- `DefinitionFile\ModelDefinition` : conserve toutes les clés supplémentaires (`traits`, `props`, `softDeletes`, `timestamps`, `description`…) via `option()` / `setOption()`.
- `backend` : `hasMigration`, `hasModel`, `hasRequest`, `hasController`, `hasRoute`, `hasPermission`, `hasFactory`, `hasTest`, `apiRoute`.

L'orchestrateur ne garde jamais `module.json` en mémoire : `mark()` relit le fichier, modifie le modèle et enregistre.

## IA

| Classe | Rôle |
|---|---|
| `Ai\GeneratorAi` | Disponibilité (config, `--no-ai`, fonctionnalité, fournisseur), `ask()` vers un agent, `attempt()` avec repli, journal |
| `Ai\Assistant` | Tâches assistées + validation/nettoyage des sorties |
| `Ai\Agents\*` | Agents `laravel/ai` à sortie structurée (`HasStructuredOutput`) |
| `Ai\Fallback\*` | Équivalents déterministes (`Labels`, `FieldSyntax`, `ValidationHints`, `FakeData`) |

Ajouter une fonction assistée :

1. créer un agent dans `Ai/Agents` (schéma strict, `withoutAdditionalProperties()`) ;
2. écrire l'équivalent déterministe dans `Ai/Fallback` ;
3. exposer la tâche dans `Assistant` via `$this->ai->attempt('<fonction>', ia, repli)` en **validant** la réponse ;
4. déclarer la fonction dans `GeneratorAi::FEATURES` et `config/generator.php` ;
5. tester avec `MonAgent::fake([...])` (voir `tests/Feature/GeneratorAiTest.php`).

Règle absolue : une sortie de l'IA ne devient jamais du code sans passer par une liste blanche ou un échappement (`var_export`).

## Configuration (`config/generator.php`)

| Clé | Rôle |
|---|---|
| `ai.*` | Activation, fournisseur, modèle, délai, interrupteurs par fonction |
| `locales` | Langues des traductions générées |
| `technical_columns` | Colonnes exclues des formulaires et imports |
| `register_autoload` | Déclaration automatique dans `composer.json` (désactivée dans les tests) |
| `format_php`, `format_frontend` | Formatage Pint / ESLint des fichiers générés |
| `seed_count` | Volume du seeder de démonstration |

## Tests

| Fichier | Couverture |
|---|---|
| `tests/Feature/GeneratorStarterTest.php` | Définition → migration réelle → fullstack sans IA → API exécutée (permissions, 201, unique, enum, recherche, pagination, suppression groupée) |
| `tests/Feature/GeneratorAiTest.php` | Repli sans IA, détection du fournisseur, agents simulés, nettoyage des sorties, échec d'appel |
| `tests/Feature/GeneratorBuildingBlocksTest.php` | Syntaxe de champs, compatibilité des définitions, migration, échappement des règles, préfixes de routes, import de table |
| `tests/Unit/Generator/*` | Modes, configurations, conversions SQL, patch de modèles |

`phpunit.xml` désactive l'IA, l'enregistrement dans `composer.json` et ESLint pendant les tests.
