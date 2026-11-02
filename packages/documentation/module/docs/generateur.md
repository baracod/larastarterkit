# Générateur CRUD

Le paquet `baracod/larastarterkit-generator`, installé en développement, crée des modules et des CRUD complets (API, écrans, permissions, tests) à partir d'une définition d'entité. Le guide détaillé est livré avec le paquet : `vendor/baracod/larastarterkit-generator/docs/USAGE.md`.

## Démarrage rapide

```sh
# 0. Créer le module si besoin
php artisan larastarterkit:module Shop --description="Boutique"

# 1. Définir l'entité et générer
php artisan larastarterkit:definition Shop Product \
  --fields="name:string:120, sku:string:40:unique, status:enum(draft|active):default=draft, price:float, category_id:fk(shop_categories)" \
  --generate=fullstack

# 2. Créer la table, puis reconstruire le frontend
php artisan migrate --no-interaction
pnpm run build
```

`php artisan larastarterkit` ouvre un menu interactif qui enchaîne ces étapes.

## Commandes

| Commande | Rôle |
| --- | --- |
| `larastarterkit` | Menu interactif : modules, modèles, génération, IA |
| `larastarterkit:module` | Crée un module prêt pour le générateur |
| `larastarterkit:definition` | Crée ou remplace la définition d'un modèle dans `module.json` |
| `larastarterkit:crud` | Génère tout ou partie du CRUD d'un modèle défini |
| `larastarterkit:ai` | État de l'assistance IA ; `--test` vérifie le fournisseur |

## Définir une entité

| Source | Option | IA requise |
| --- | --- | --- |
| Liste de champs | `--fields="nom:type:modificateur, …"` | non |
| Description métier | `--describe="…"` | oui |
| Table existante | `--from-table=shop_products` | non |

Types : `string`, `text`, `integer`, `float`, `boolean`, `date`, `datetime`, `json`, `uuid`, `enum(a|b)`, `fk(table)`. Modificateurs : `nullable`, `unique`, longueur (`120`), `default=…`, `label=…`. Options : `--table=`, `--soft-deletes`, `--force`.

## Générer

```sh
php artisan larastarterkit:crud Shop product --strategy=fullstack
```

| Stratégie | Produit |
| --- | --- |
| `backend` | migration, modèle, requête de validation, contrôleur, routes protégées, permissions, factory, test |
| `frontend` | types, client API, liste, formulaire, menu, traductions |
| `fullstack` | les deux (défaut) |

`--modes=` choisit des éléments précis. Un élément déjà généré est ignoré sauf avec `--force`.

L'API générée suit la convention `GET|POST /api/v1/<module>/<ressources>`, `GET|PUT|DELETE …/{id}` et `POST …/bulk-delete`, protégées par `ability:browse|add|edit|delete,<table>`. La liste accepte `search`, `sort`, `direction` et `per_page`.

## Garanties

- Aucune migration destructive : une migration existante n'est jamais réécrite, une table existante jamais recréée.
- Les permissions sont écrites dans le seeder du module et appliquées à la base courante.
- Le code métier existant (contrôleurs, factories, tests) n'est remplacé qu'avec `--force`.
- Les générateurs refusent de modifier un module installé dans `vendor/`. Un CRUD généré appartient à l'application : les mises à jour du kit ne le réécrivent pas.

## Assistance IA

Facultative, via le Laravel AI SDK : traductions des libellés, conception d'entité depuis une description, règles de validation, données de démonstration. Une clé de fournisseur suffit (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`…). Les réglages `GENERATOR_AI_ENABLED`, `GENERATOR_AI_PROVIDER`, `GENERATOR_AI_MODEL` et les interrupteurs par fonction sont décrits dans le guide du paquet. La sortie de l'IA est toujours validée ; en cas d'erreur, les règles déterministes prennent le relais.

## Gabarits

Les fichiers produits viennent des gabarits (`stubs`) du générateur. Après génération, le code est formaté par Pint (PHP) et ESLint (TypeScript/Vue).
