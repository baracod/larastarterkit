# Baracod Larastarterkit — dépôt de développement

Starter modulaire Laravel 12, Vue 3, Vuetify et TypeScript.

- **Auth** : comptes, profils, rôles, permissions, sécurité.
- **Admin** : paramètres, modules, notifications et diagnostics.
- **Outils documentaires partagés** : import, génération PDF, signatures, aperçu et historique ; intégrations extensibles, sans module Documents.
- **Documentation** : site public VitePress indépendant.

Auth et Admin sont obligatoires. Les autres modules peuvent être désactivés. Les composants Sneat/Core, générateurs CRUD, cartes, QR, outils IA et services temps réel restent disponibles.

## Distribution et mises à jour

Le squelette distribuable est dans `skeleton/`. Il installe **core et generator directement** : le socle en dépendance de production, les générateurs en dépendance de développement. Documentation reste facultative.

```sh
# Après publication de la version 1.0 sur la fiche Packagist existante
composer create-project baracod/larastarterkit mon_application "1.0.*"
```

Chaque package Composer contient son backend **et son frontend**. Aucun package npm `@baracod/*` n'est nécessaire. `larastarterkit:frontend` compose les dépendances JavaScript des packages installés ; pnpm les verrouille et Vite compile les sources depuis `vendor/` et les modules locaux. Ne modifiez pas `vendor/` : les personnalisations résident dans l'application.

Le dépôt de développement utilise des dépendances Composer `path` et des liens symboliques vers `packages/`. Une application distribuée utilise les mêmes packages sous `vendor/`, sans dépendre de ce dépôt. Voir [le contrat des packages](docs/distribution.md) et [le changelog](CHANGELOG.md).

Pour contribuer ou comprendre la structure du dépôt, consulter [CONTRIBUTING.md](CONTRIBUTING.md).

## Branches

- `dev` : développement et intégration.
- `prod` : sources complètes validées depuis `dev`.
- `main` : squelette distribuable destiné à Packagist.

Les tests GitHub Actions vérifient `dev` et `prod`. La livraison est manuelle ; ce dépôt ne contient pas de scripts Python de publication. Voir [CONTRIBUTING.md](CONTRIBUTING.md).

## Démarrage

Sur une base vierge, configurez `.env` depuis `.env.example`, puis :

```sh
composer install
pnpm install --frozen-lockfile
php artisan key:generate --no-interaction
php artisan migrate --seed --force --no-interaction
php artisan auth:super-admin:create --name="Administrator" --email="admin@example.test"
php artisan storage:link --no-interaction
pnpm run dev
```

Le seeding ne crée pas de compte administrateur. La commande `auth:super-admin:create` demande un mot de passe. Pour le réinitialiser ensuite : `php artisan auth:super-admin:password --email="admin@example.test"`. Le rôle `administrator` possède tous les droits actuels et futurs ; `starter:super-admin` reste un alias compatible. Configurez `APP_NAME`, `APP_LOGO`, `APP_URL` et `DOCUMENTATION_URL` pour votre application.

Sur MySQL/MariaDB, cette commande crée la base configurée par `DB_DATABASE` si elle manque et si `DB_USERNAME` dispose du droit de création. Elle applique uniquement les migrations en attente. `DatabaseSeeder` appelle les seeders des modules : Auth crée les rôles `administrator` et `user` et ses permissions ; Admin initialise ses permissions et paramètres ; les modules optionnels installés ajoutent leurs permissions, même désactivés, afin de préparer leur activation ultérieure. Toutes ces permissions sont rattachées au rôle `administrator`. Les seeders peuvent être relancés sans dupliquer les données ni remplacer les paramètres existants.

## Documentation

[Installation](Modules/Documentation/docs/installation.md) · [Modules et générateurs](Modules/Documentation/docs/modules.md) · [Documents](Modules/Documentation/docs/documents.md) · [Tests](Modules/Documentation/docs/tests.md) · [Déploiement](Modules/Documentation/docs/deployment.md)

```sh
pnpm --dir Modules/Documentation install --frozen-lockfile
pnpm --dir Modules/Documentation run docs:dev
pnpm --dir Modules/Documentation run docs:build
```

## Validation

```sh
vendor/bin/phpunit
pnpm run lint
pnpm run typecheck
pnpm run build
```

PHPUnit utilise SQLite en mémoire. Pour MariaDB, fournissez une base de test dédiée via `DB_CONNECTION=mysql DB_DATABASE=...`. Les changements d'installation de modules nécessitent une reconstruction du frontend et un redémarrage des workers.

### Documentation locale et e-mails Auth

Pour servir la documentation sur `/docs/` avec le serveur Laravel :

```bash
pnpm --dir Modules/Documentation run docs:build:local
```

Les e-mails Auth utilisent la file `auth-mail`. Par défaut, elle utilise la base de données (Redis si `QUEUE_CONNECTION=redis`). `AUTH_MAIL_CONNECTION` permet de choisir explicitement la connexion. En local, démarrez le worker :

```bash
php artisan queue:work database --queue=auth-mail --no-interaction
```

Configurez SMTP avant de traiter les e-mails. La connexion utilisateur ne dépend pas d'un envoi SMTP immédiat. Avec Redis en production, Horizon traite déjà la file `auth-mail`.

## CMS Documentation

Le module Documentation permet de rédiger en Markdown, gérer des éditions et révisions, importer des images et publier un portail VitePress depuis la base de données. Voir [le guide CMS](Modules/Documentation/docs/cms.md) pour l'installation, les permissions, la file dédiée et les images Docker.
