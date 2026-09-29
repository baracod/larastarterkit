# Installation

Prérequis : PHP 8.4 et extensions Composer, Composer 2, Node LTS, pnpm 9.12.2. MySQL/MariaDB pour le déploiement habituel ; SQLite pour les tests.

Cette distribution vise une base vierge. Ne lancez pas ces migrations sur une base d'une autre application.

```sh
cp .env.example .env
composer install
pnpm install --frozen-lockfile
php artisan key:generate --no-interaction
php artisan migrate --seed --force --no-interaction
php artisan auth:super-admin:create --name="Administrator" --email="admin@example.test"
php artisan storage:link --no-interaction
pnpm run dev
```

La commande administrateur demande le mot de passe ; pour une automatisation, ses options sont décrites par `php artisan help auth:super-admin:create --no-interaction`. Le seeding ne crée aucun compte de production.

Configurez `APP_NAME`, `APP_URL`, la connexion SQL, le mail et, si nécessaire, Redis et Reverb. `APP_LOGO` personnalise le logo de l'application. Les migrations et le catalogue sont génériques ; le catalogue est idempotent.

Sur MySQL/MariaDB, `migrate --seed --force --no-interaction` crée `DB_DATABASE` si elle n'existe pas, avec les droits du compte `DB_USERNAME`. Sur une installation existante de ce starter, seules les migrations en attente sont exécutées : aucune remise à zéro n'est nécessaire.

Les migrations applicatives sont dans `Modules/<Module>/database/migrations`. Admin possède aussi les tables d'infrastructure (cache, files d'attente, notifications et journal d'activité). Le nom des migrations déplacées est conservé pour respecter l'historique des installations existantes.

Les seeders sont dans `Modules/<Module>/database/seeders` :

- Auth : `AuthDatabaseSeeder`, `RoleSeeder` et `PermissionSeeder` créent les rôles `administrator` et `user`, puis les permissions Auth.
- Admin : `AdminDatabaseSeeder`, `PermissionSeeder` et `SettingSeeder` initialisent les accès Admin/tableau de bord et les paramètres par défaut.
- Documentation : son `PermissionSeeder` crée les permissions dès que le module est installé, même désactivé.

Le `DatabaseSeeder` racine orchestre ces appels, en commençant par Auth. Chaque seeder de permissions initialise les rôles manquants avant de rattacher ses permissions à `administrator` ; il fonctionne donc aussi indépendamment de l’ordre des modules avec `module:seed`. Les relances ne créent pas de doublons et préservent les paramètres existants. Pour exécuter un seeder de module après cette initialisation : `php artisan db:seed --class='Modules\Admin\Database\Seeders\AdminDatabaseSeeder' --force --no-interaction`.

Les référentiels mondiaux sont optionnels : les migrations du paquet World sont découvertes automatiquement ; exécuter `php artisan db:seed --class='Modules\Admin\Database\Seeders\WorldDataSeeder' --no-interaction`. Le starter ne charge pas automatiquement les villes du monde.

## Super administrateur

Les commandes sont fournies par le module Auth. Le rôle `administrator` est le rôle de super administrateur du starter : il possède tous les droits actuels et futurs, dans les contrôles backend et dans CASL (`manage/all`).

Créer le compte, ou rattacher un compte existant au rôle :

```sh
php artisan auth:super-admin:create --name="Super Administrateur" --email="admin@example.test"
```

Le mot de passe est demandé par une saisie masquée pour un nouveau compte. Pour un compte existant, il reste inchangé si `--password` n’est pas fourni. Les options `--username` et `--reset-access` permettent de préciser le pseudonyme et de reconstruire les permissions du rôle. L’ancienne commande `starter:super-admin` reste un alias compatible.

Initialiser ou réinitialiser le mot de passe d’un super administrateur existant :

```sh
php artisan auth:super-admin:password --email="admin@example.test"
```

Le nouveau mot de passe est demandé deux fois, avec saisie masquée. Il doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un symbole, dans la limite de 72 octets. Cette commande révoque les jetons API, les anciens liens de réinitialisation et les sessions stockées en base ; elle renouvelle aussi le jeton « se souvenir de moi ». Le contrôle de session Sanctum invalide les autres sessions lors de leur prochaine requête. Les rôles et l’état actif/suspendu du compte sont conservés.

Pour l’automatisation, les deux commandes acceptent `--email`, `--password` et `--no-interaction` ; la création accepte également `--name`. La commande de mot de passe échoue si le compte n’existe pas ou ne possède pas le rôle `administrator`.

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
