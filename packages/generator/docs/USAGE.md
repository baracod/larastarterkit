# Générateur CRUD STARTER — Guide d'utilisation

> Décrivez une entité, le générateur produit la migration, le modèle, la validation, le contrôleur, les routes protégées, les permissions, une factory, un test d'API, puis le frontend (types, client API, liste, formulaire, menu, traductions).
> L'IA (Laravel AI SDK) est **facultative** : sans clé, tout fonctionne avec des règles déterministes.

## Sommaire

1. [Démarrage rapide](#démarrage-rapide)
2. [Commandes](#commandes)
3. [Définir une entité](#définir-une-entité)
4. [Générer](#générer)
5. [Assistance IA](#assistance-ia)
6. [Fichiers produits](#fichiers-produits)
7. [Règles et garanties](#règles-et-garanties)
8. [Dépannage](#dépannage)

---

## Démarrage rapide

```bash
# 0. Créer le module (si besoin)
php artisan larastarterkit:module Shop --description="Boutique"

# 1. Définir l'entité (sans IA, syntaxe de champs)
php artisan larastarterkit:definition Shop Product \
  --fields="name:string:120, sku:string:40:unique, status:enum(draft|active):default=draft, price:float, category_id:fk(shop_categories)" \
  --generate=fullstack

# 2. Créer la table, puis reconstruire le frontend
php artisan migrate --no-interaction
pnpm run build
```

Avec l'IA configurée, une description suffit :

```bash
php artisan larastarterkit:definition Shop --describe="Produits du catalogue : nom, référence unique, prix, stock et catégorie" --generate=fullstack
```

---

## Commandes

| Commande | Rôle |
|---|---|
| `php artisan larastarterkit` | Menu interactif complet : modules, modèles, génération, IA |
| `php artisan larastarterkit:module` | Crée un module prêt pour le générateur |
| `php artisan larastarterkit:definition` | Crée ou remplace la définition d'un modèle (champs, contraintes, relations) |
| `php artisan larastarterkit:crud` | Génère tout ou partie du CRUD d'un modèle défini |
| `php artisan larastarterkit:ai` | État de l'IA ; `--test` vérifie le fournisseur |

Options communes : `--no-ai` (règles uniquement), `--no-interaction`. Toutes les commandes : `php artisan list larastarterkit`.

Anciens noms toujours acceptés : `larastarterkit:builder`, `larastarterkit:builder-module`, `larastarterkit:make`, `generate:crud`, `generate:definition`, `generator:ai`.

---

## Définir une entité

Chaque modèle est décrit dans `Modules/{Module}/module.json` (clé `models`). Trois sources possibles :

| Source | Commande | IA requise |
|---|---|---|
| Liste de champs | `--fields="…"` | non |
| Description métier | `--describe="…"` | oui (sinon utilisez la syntaxe de champs) |
| Table existante | `--from-table=shop_products` | non |

### Syntaxe de champs

`nom:type:modificateur:modificateur, …`

| Types | Modificateurs |
|---|---|
| `string`, `text`, `integer`, `float`, `boolean`, `date`, `datetime`, `json`, `uuid` | `nullable` — champ facultatif |
| `enum(a\|b\|c)` — liste fermée | `unique` — valeur unique en base |
| `fk(table)` — clé étrangère (relation belongsTo) | `120` — longueur maximale (chaînes) |
| alias : `int`, `bool`, `decimal`, `timestamp`… | `default=valeur`, `label=Libellé` |

Autres options : `--table=` (nom de table, défaut `<module>_<pluriel>`), `--soft-deletes`, `--force` (remplacer une définition).

L'import d'une table reprend les vraies contraintes : nullabilité, index uniques, valeurs d'énumération, clés étrangères, `deleted_at`.

---

## Générer

```bash
php artisan larastarterkit:crud <Module> <cle-modele> [--strategy=backend|frontend|fullstack] [--modes=…] [--force] [--no-ai] [--batch]
```

| Alias `--modes` | Produit |
|---|---|
| `migration` | Migration de création de table (jamais réécrite ; ignorée si la table existe) |
| `model` | Modèle Eloquent (casts, relations, SoftDeletes, factory) |
| `request` | FormRequest (présence, type, unique, exists, énumérations, longueurs, règles métier) |
| `controller` | Contrôleur REST : liste (recherche, tri, pagination optionnelle), 201 à la création, suppression groupée |
| `route` | Routes protégées `ability:browse/add/edit/delete,<table>` + `POST …/bulk-delete` |
| `permissions` | Seeder `GeneratedPermissionSeeder` du module + application à la base |
| `factory` | Factory + seeder de démonstration |
| `test` | Test PHPUnit de l'API (authentification, permissions, CRUD, validation, suppression groupée) |
| `types`, `api`, `index`, `form`, `menu`, `i18n` | Frontend, élément par élément |

Stratégies : `backend` (8 modes), `frontend` (6 modes), `fullstack` (14 modes, défaut).
Un élément déjà généré est ignoré sauf avec `--force`. Le rapport final indique, pour chaque élément, *généré / ignoré / échec*, les fichiers et la provenance (IA ou règles) des contenus assistés.

### API générée

| Requête | Permission |
|---|---|
| `GET /api/v1/<module>/<ressources>?search=&sort=&direction=&per_page=` | `browse` |
| `GET /api/v1/<module>/<ressources>/{id}` | `browse` |
| `POST /api/v1/<module>/<ressources>` → 201 | `add` |
| `PUT /api/v1/<module>/<ressources>/{id}` | `edit` |
| `DELETE /api/v1/<module>/<ressources>/{id}` | `delete` |
| `POST /api/v1/<module>/<ressources>/bulk-delete` `{ "ids": [..] }` | `delete` |

Sans `per_page`, la liste complète est renvoyée (compatible avec les écrans générés).

---

## Assistance IA

Le générateur utilise le [Laravel AI SDK](https://laravel.com/docs/ai-sdk) (`laravel/ai`) avec des agents à sortie structurée :

| Fonction | Rôle | Sans IA |
|---|---|---|
| `translations` | Libellés FR/EN des titres, menus et champs | Dictionnaire des champs courants + mise en forme des noms |
| `design` | Conception d'une entité depuis une description | Syntaxe de champs |
| `validation` | Règles de format et de bornes (email, url, min, max…) | Heuristiques sur les noms et types |
| `fake_data` | Données de démonstration réalistes | Correspondances nom/type → Faker |

**Garanties** : la sortie de l'IA est toujours validée — règles de validation issues d'une liste blanche, formateurs Faker issus d'une liste fermée, noms et types vérifiés, relations limitées aux tables existantes, libellés nettoyés. Aucun texte produit par l'IA n'est écrit tel quel dans du code. En cas d'erreur (réseau, quota, réponse invalide), la règle déterministe prend le relais et la génération continue.

### Configuration (`.env`)

```dotenv
# Une clé suffit ; le fournisseur est choisi automatiquement (Anthropic, OpenAI, Gemini, Mistral…)
ANTHROPIC_API_KEY=...
# ou OPENAI_API_KEY=... / GEMINI_API_KEY=... (clé déjà utilisée par l'ancien générateur)

# Facultatif
GENERATOR_AI_PROVIDER=anthropic     # impose un fournisseur de config/ai.php (ollama pour un modèle local)
GENERATOR_AI_MODEL=                 # modèle précis, sinon celui par défaut du fournisseur
GENERATOR_AI_ENABLED=true           # false : jamais d'IA
GENERATOR_AI_TRANSLATIONS=true      # interrupteurs par fonction : _DESIGN, _VALIDATION, _FAKE_DATA
```

Puis `php artisan config:clear --no-interaction` et `php artisan larastarterkit:ai --test`.

---

## Fichiers produits

```
Modules/Shop/
├── app/Models/Product.php
├── app/Http/Requests/ProductRequest.php
├── app/Http/Controllers/ProductController.php
├── routes/api.php                                  ← routes insérées avant //{{ next-route }}
├── database/migrations/…_create_shop_products_table.php
├── database/factories/ProductFactory.php
├── database/seeders/ProductSeeder.php              ← données de démo (appel explicite)
├── database/seeders/GeneratedPermissionSeeder.php  ← régénéré depuis module.json
├── tests/Feature/ProductApiTest.php
└── resources/ts/
    ├── types/entities.d.ts        ← IProduct
    ├── api/Product.ts             ← ProductAPI + ProductPayload
    ├── pages/products/index.vue   ← route « shop-products »
    ├── components/ShopProductAddOrEdit.vue
    ├── menuItems.json
    └── locales/fr.json, en.json   ← Shop.product.field.*
```

Les fichiers PHP générés sont formatés avec Pint, les fichiers TypeScript/Vue avec ESLint.

---

## Règles et garanties

1. **Aucune migration destructive** : une migration existante n'est jamais réécrite ; une table existante n'est jamais recréée.
2. **Permissions reproductibles** : elles sont écrites dans le seeder du module (nouvelles installations) et appliquées à la base courante.
3. **Module créé = module utilisable** : espaces de noms déclarés dans `composer.json` (app, factories, seeders), routes protégées par `EnsureModuleEnabled`, `active` et `must_change_pass`.
4. **État fiable** : `module.json` est relu avant chaque mise à jour ; les indicateurs (`backend.hasModel`, `frontend.hasApi`…) ne s'écrasent plus.
5. **Code métier préservé** : contrôleurs, factories et tests existants ne sont remplacés qu'avec `--force`.
6. **Relations obligatoires des factories** : un enregistrement lié existant est réutilisé ; sinon, la factory du modèle lié est résolue à l'exécution pour le créer. En l'absence de données et de factory utilisable, une exception explicite indique la relation à renseigner avant toute insertion avec une clé nulle. Une valeur fournie à `create(['owner_id' => …])` prime sur cette résolution ; les relations facultatives peuvent rester nulles.

---

## Dépannage

| Symptôme | Solution |
|---|---|
| « Fichier de définition introuvable » | Le module n'existe pas ou n'a pas de `module.json`. |
| « Marqueur //{{ next-route }} absent » | Ajoutez le marqueur dans le groupe de routes de `routes/api.php`. |
| « Sans IA, indiquez le nom du modèle… » | Configurez un fournisseur (`larastarterkit:ai`) ou utilisez la syntaxe de champs. |
| Relation ignorée | Aucun modèle ne porte la table ciblée : générez-le d'abord, ou créez la relation plus tard. |
| Classes introuvables après création d'un module | `composer dump-autoload`, puis redémarrez les workers (`php artisan queue:restart`). |
| Écran absent | `pnpm run build` (ou `pnpm run dev`). |
