# Créer un module

Un module regroupe une fonctionnalité : backend, frontend, permissions et traductions. Le plus simple est de le créer avec le [générateur](./generateur) :

```sh
php artisan larastarterkit:module Shop --description="Boutique"
composer dump-autoload
```

## Structure

```
Modules/Shop/
├── module.json                 ← manifeste
├── composer.json
├── app/                        ← namespace Modules\Shop\
│   ├── Http/Controllers/
│   ├── Models/
│   └── Providers/ShopServiceProvider.php
├── routes/api.php
├── database/migrations/, seeders/, factories/
├── lang/ ou resources/lang/
├── tests/Feature/
└── resources/ts/
    ├── pages/                  ← routées sous /shop/…
    ├── components/             ← auto-importés
    ├── composable/             ← auto-importés
    ├── api/, types/
    ├── locales/fr.json, en.json
    └── menuItems.json          ← menu du module
```

L'espace de noms PHP `Modules\Shop\` pointe vers `Modules/Shop/app/`. Enregistrez cet autoload dans le `composer.json` de l'application (le générateur le fait), puis `composer dump-autoload`.

## Le manifeste `module.json`

```json
{
  "name": "Shop",
  "alias": "shop",
  "description": "Boutique",
  "priority": 0,
  "providers": ["Modules\\Shop\\Providers\\ShopServiceProvider"],
  "requires": ["Catalog"],
  "frontend": { "path": "resources/ts" },
  "upgradeSeeders": ["Modules\\Shop\\Database\\Seeders\\GeneratedPermissionSeeder"],
  "navigation": {
    "title": "Boutique", "icon": "mdi-store", "module": "Shop",
    "action": "browse", "subject": "shop", "to": { "name": "shop" }
  }
}
```

| Clé | Rôle |
| --- | --- |
| `providers` | Providers Laravel chargés quand le module est actif |
| `requires` | Modules nécessaires |
| `frontend.path` | Dossier des sources frontend (doit rester dans le module) |
| `upgradeSeeders` | Seeders idempotents relancés par `larastarterkit:upgrade` (permissions, réglages par défaut) |
| `navigation` | Entrée du module dans le catalogue et la navigation |
| `models` | Définitions utilisées par le générateur CRUD |

Les noms de modules sont uniques, y compris entre modules locaux et paquets Composer.

## Frontend du module

- Les pages de `resources/ts/pages` sont routées sous le nom du module en minuscules : `pages/products/index.vue` devient `/shop/products`, nommée `shop-products`.
- L'alias `@shop` désigne `resources/ts` du module.
- Les composants de `components/` et les composables de `composable/` sont auto-importés, comme Vue, Pinia et vue-i18n.
- Les textes visibles passent par `t()` et les fichiers `locales/*.json` ; les appels HTTP par le client `$api` (ofetch) ; les dates par dayjs.
- `menuItems.json` décrit le menu ; chaque entrée porte `action` et `subject` pour n'apparaître qu'aux utilisateurs autorisés.

## Activer, désactiver

Auth et Admin sont toujours actifs. Les autres modules s'activent dans Admin → Modules ou par `php artisan module:enable Shop --no-interaction`. L'état est stocké dans `modules_statuses.json`. Côté API, protégez les routes d'un module optionnel avec le middleware `EnsureModuleEnabled` ; le frontend lit la disponibilité des modules par `/api/v1/modules`. La désactivation conserve les données.

## Règles

- Un module optionnel ne doit pas être importé directement par Auth, Admin ou le socle.
- Entre modules, préférez un contrat (interface PHP liée par défaut avec `bindIf`) à un import direct : le module consommateur fonctionne même si l'autre est absent.
- Ajouter ou retirer **physiquement** un module impose de régénérer l'autoload, de reconstruire le frontend et de redémarrer les workers.

## Distribuer un module

Un module peut devenir un paquet Composer réutilisable :

```sh
php artisan larastarterkit:package Shop mon-organisation/shop --no-interaction
```

Le paquet déclare ses modules dans `extra.larastarterkit.modules` de son `composer.json` et porte ses dépendances JavaScript dans un `package.json` privé. Voir [Mettre à jour](./distribution).
