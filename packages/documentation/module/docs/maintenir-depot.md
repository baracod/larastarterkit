# Maintenir : le dépôt

Cette partie s'adresse aux personnes qui font évoluer `baracod/larastarterkit` lui-même. Pour construire une application, voir [Concepts](./concepts).

## Un monorepo

Tout le code est développé dans le dépôt `larastarterkit`, branche `dev`. Les dépôts `larastarterkit-core` et `larastarterkit-generator` sont des miroirs en lecture seule, alimentés par la CI : on n'y pousse jamais.

| Répertoire | Contenu | Publié comme |
| --- | --- | --- |
| `packages/core/` | Infrastructure (`src/`), modules Auth et Admin (`modules/`), frontend commun et configuration Vite (`frontend/`), vues, configuration | `baracod/larastarterkit-core` |
| `packages/generator/` | Commandes, gabarits (`stubs/`) et guide du générateur | `baracod/larastarterkit-generator` |
| `packages/documentation/` | Module Documentation : CMS, site VitePress, ces pages (`module/docs/`) | pas encore publié |
| `skeleton/` | Fichiers propres à une nouvelle application | `baracod/larastarterkit` (branche `main`) |
| `tests/` | Tests d'intégration et de régression du dépôt | — |
| `docker/`, `docker-compose.*.yml` | Développement et déploiement de l'application de référence | — |
| `bin/` | Outils du dépôt (`set-version.php`…) | — |

La racine est une **application de référence** : elle installe les paquets par des dépôts Composer `path` (`packages/*`) et des liens symboliques permettent de travailler directement sur leurs sources :

| Lien | Cible |
| --- | --- |
| `Modules/Auth`, `Modules/Admin` | `packages/core/modules/…` |
| `Modules/Documentation` | `packages/documentation/module` |
| `resources/ts`, `resources/styles`, `resources/lang`, `resources/images`, `resources/views` | `packages/core/frontend/…`, `packages/core/views` |
| `stubs` | `packages/generator/stubs` |
| `themeConfig.ts` | `packages/core/frontend/themeConfig.ts` |

Conséquence : dans ce dépôt, `resources/ts` **est** le frontend du socle. Un fichier ajouté « dans l'application » (par exemple `resources/ts/starter.ts`) atterrit dans `packages/core`. Pour tester un point d'extension comme une vraie application, utilisez un fichier temporaire et supprimez-le, ou une application créée depuis `skeleton/`.

## Environnement de travail

```sh
composer install
pnpm install
cp .env.example .env   # base de développement dédiée
php artisan key:generate --no-interaction
php artisan migrate --seed --force --no-interaction
php artisan auth:super-admin:create --name="Administrator" --email="admin@example.test"
pnpm run dev
```

Utilisez une base de développement dédiée ; ne lancez jamais les migrations du starter sur la base d'une autre application.

## Où placer quoi

- Migrations, seeders, traductions et frontend vont dans le paquet ou le module responsable, jamais à la racine.
- Auth et Admin ne dépendent d'aucun module métier ni optionnel.
- Les espaces de noms des modules pointent vers leur dossier `app/` et sont déclarés explicitement dans l'autoload Composer du paquet.
- Le code PHP lit la configuration avec `config()` ; `env()` n'apparaît que dans les fichiers `config/*.php`.
- Textes visibles : i18n (`t()` côté frontend, `__('module::fichier.clé')` côté backend), en français et en anglais.
- Frontend : Composition API, ofetch (`$api`), dayjs, composants Core.
- Les identités de permission sont `key` + `action`/`subject` : le backend et CASL doivent rester cohérents.
- Documents : stockage privé, contrôle d'autorisation sur le dossier, versions précédentes conservées.
- Préservez les composants réutilisables et les générateurs : une application peut en dépendre.

## Branches

| Branche | Rôle |
| --- | --- |
| `dev` | Développement et intégration |
| `prod` | Sources stables, validées depuis `dev` ; chaque push recopie core et generator vers leurs miroirs |
| `main` | Squelette distribuable lu par Packagist ; alimentée uniquement par le workflow de publication |

On ne fusionne jamais dans `main`. Voir [Publier une version](./maintenir-publication).

## Ajouter ou retirer un module physique

Régénérez l'autoload (`composer dump-autoload`), reconstruisez le frontend et redémarrez les workers : Vite et le registre des modules lisent la liste des modules au démarrage.
