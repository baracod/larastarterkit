# Architecture distribuée

## Packages Composer

| Package | Inclus |
| --- | --- |
| `baracod/larastarterkit` | Squelette applicatif, configuration et commandes d'installation |
| `baracod/larastarterkit-core` | Infrastructure, Auth/Admin, interface commune et ressources Vite |
| `baracod/larastarterkit-generator` | Générateurs et stubs, installé automatiquement en développement |
| `baracod/larastarterkit-documentation` | CMS, interface, Markdown, Mermaid et outils VitePress, facultatif |

Le frontend voyage dans la même archive et porte donc la même version que le backend. Aucun registre npm Baracod n'est utilisé. Les dépendances externes Vue, Vuetify, etc. restent installées avec pnpm. Vite partage leurs instances avec les modules. Le squelette ne copie pas les écrans du socle.

## Installer

```sh
composer create-project baracod/larastarterkit mon_application "1.0.*"
cd mon_application
# Configurer .env : SQLite par défaut, ou une nouvelle base MySQL/MariaDB.
pnpm install
php artisan larastarterkit:install --no-interaction
php artisan auth:super-admin:create --email=admin@example.test --name=Administrator
pnpm run build
```

Aucun mot de passe d'administrateur n'est prédéfini. Composer ne lance aucune migration. `install` est explicite et appelle les migrations et seeders d'évolution idempotents. Pour la RC, utiliser sa version exacte à la place de `1.0.*`.

## Mettre à jour une application

Dans une branche de l'application, modifier les contraintes Composer pour les versions souhaitées, puis :

```sh
composer update baracod/larastarterkit-core baracod/larastarterkit-generator --with-all-dependencies
php artisan larastarterkit:frontend --no-interaction
pnpm install
php artisan larastarterkit:doctor --json --no-interaction
php artisan larastarterkit:upgrade --dry-run --no-interaction
php artisan larastarterkit:upgrade --no-interaction
pnpm run build
pnpm run typecheck
```

Tester puis enregistrer `composer.lock` et `pnpm-lock.yaml`. Au déploiement, installer les versions verrouillées, construire les assets, appliquer les évolutions avec les identifiants de maintenance et redémarrer les workers. Les builds frontend ont besoin des packages Composer et de PHP. Le répertoire généré `.larastarterkit/` est recréé après l'installation Composer.

`doctor` vérifie la cohérence des manifestes JavaScript, les modules et la connexion/migrations. Les incompatibilités PHP et Composer sont bloquées par Composer. Les contraintes JavaScript divergentes entre modules sont refusées explicitement : aligner leurs manifestes avant de poursuivre. Les seeders ne doivent ni supprimer des données ni réinitialiser les paramètres déjà définis. En cas d'échec, corriger puis relancer : Laravel conserve les migrations terminées et les seeders sont idempotents.

## Modules et extension

Un module local réside dans `Modules/Nom` et déclare `module.json`, son namespace `Modules\\Nom\\` vers `app/`, ses providers, ses dépendances `requires` et ses ressources `resources/ts`. Enregistrer son autoload Composer explicitement. Auth/Admin sont toujours actifs ; les autres sont activés explicitement.

Un package Composer déclare les chemins de ses modules dans :

```json
{"extra":{"larastarterkit":{"modules":["module"]}}}
```

Chaque `module.json` peut déclarer `navigation`, `frontend: {"path":"resources/ts"}` et `upgradeSeeders`. Les dépendances PHP/version du socle figurent dans le `require` Composer. Le `package.json` privé à la racine du package contient les dépendances JavaScript. Les noms de modules sont uniques, y compris entre packages et modules locaux.

`php artisan larastarterkit:package MonModule mon-organisation/mon-module --no-interaction` prépare le manifeste distribuable d'un module existant. Les générateurs refusent de modifier un module installé dans vendor. Les CRUD déjà générés restent propriété de l'application et ne sont pas réécrits par les mises à jour.

Les ressources frontend du module comprennent `pages/`, `components/`, `menuItems.json`, `locales/*.json`, et éventuellement `components/ModuleNavbar.vue`. Les contributions utilisent les mêmes permissions action/subject que le backend. Après installation ou suppression physique : régénérer l'autoload, synchroniser pnpm, reconstruire et redémarrer les workers.

Le fichier applicatif `resources/ts/starter.ts` exporte `menus`, `catalog`, `slots` et `configure(app)`. Il peut contribuer à la navigation, injecter des composants (slot `navbar-start`) et enregistrer des services/événements via `app.provide` ou `app.use`. Les pages locales restent dans `resources/ts/pages`. Utiliser `@app` pour les sources applicatives et `@` pour l'interface commune. La marque se configure avec `APP_NAME` et `APP_LOGO`.

## Documentation facultative

```sh
composer require baracod/larastarterkit-documentation:"^1.0"
php artisan module:enable Documentation --no-interaction
pnpm install
php artisan larastarterkit:upgrade --no-interaction
pnpm run build
```

Le worker `documentation` a besoin de PHP, Node et des dépendances pnpm de l'application. Les exports/publications sont stockés sur le volume partagé avec le serveur statique. Le frontend et les outils VitePress font partie du package Documentation, sans installation npm Baracod séparée.

## Transition 0.x

La publication initiale remplace l'ancien historique du dépôt `baracod/larastarterkit` par un seul commit et supprime ses anciens tags et branches. Cette décision concerne uniquement le passage initial à cette génération ; les versions publiées ensuite restent immuables. Les anciennes installations `composer require` ne sont pas converties automatiquement. Les nouvelles applications utilisent `composer create-project`.

## Maintenance du dépôt

Les changements sont développés sur `dev`, vérifiés par les tests, puis intégrés dans `prod`. Les packages Composer et le squelette sont publiés manuellement depuis leurs répertoires respectifs ; aucune publication automatique n’est déclenchée par une fusion. Les scripts Python de construction et de publication ont été retirés.
