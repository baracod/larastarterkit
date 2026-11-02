# Commandes

Toutes les commandes acceptent `--no-interaction`, à utiliser en automatisation. `php artisan help <commande> --no-interaction` détaille leurs options.

## Cycle de vie de l'application

| Commande | Rôle |
| --- | --- |
| `larastarterkit:install` | Initialise une application configurée : migrations et seeders, sans créer de compte |
| `larastarterkit:upgrade` | Applique les migrations en attente et les `upgradeSeeders` des modules actifs ; `--dry-run` liste ce qui serait fait |
| `larastarterkit:doctor` | Vérifie versions backend/frontend, modules, connexion et migrations en attente ; `--json` pour l'automatisation |
| `larastarterkit:frontend` | Compose les dépendances JavaScript des paquets installés et génère `.larastarterkit/` (tsconfig, alias) |
| `larastarterkit:modules` | Exporte les métadonnées des modules pour l'outillage frontend (`--json`) |
| `larastarterkit:package` | Prépare un module local comme paquet Composer, frontend compris |

## Comptes

| Commande | Rôle |
| --- | --- |
| `auth:super-admin:create` | Crée le super administrateur ou rattache un compte au rôle `administrator` (alias `starter:super-admin`) |
| `auth:super-admin:password` | Initialise ou réinitialise le mot de passe d'un super administrateur et révoque ses sessions |
| `auth:clear-resets` | Supprime les jetons de réinitialisation expirés |

## Modules et générateur

| Commande | Rôle |
| --- | --- |
| `module:enable`, `module:disable` | Active ou désactive un module optionnel |
| `larastarterkit` | Menu interactif du générateur |
| `larastarterkit:module` | Crée un module |
| `larastarterkit:definition` | Définit un modèle |
| `larastarterkit:crud` | Génère un CRUD |
| `larastarterkit:ai` | État de l'assistance IA |

## Base de données (exploitation)

| Commande | Rôle |
| --- | --- |
| `starter:runtime-database` | Crée ou étend un compte SQL restreint pour l'exécution, depuis une connexion de maintenance séparée |
| `starter:runtime-access` | Vérifie que la connexion de l'application n'a pas de privilèges de migration ou d'administration |
| `starter:restore-database` | Réinitialise la base (migrations et seeders selon l'environnement) ; **efface la base cible**, confirmation demandée si elle contient des tables |

## Documentation (module facultatif)

| Commande | Rôle |
| --- | --- |
| `documentation:import` | Importe les fichiers Markdown du starter dans le CMS sans écraser les pages existantes ; les groupes de la barre latérale deviennent des sections. `--collection`, `--title`, `--description` et `--edition` choisissent la documentation cible (défaut : « Sneat Starter », `v1`) |
| `documentation:recover` | Reprend les publications interrompues |

## Frontend

| Commande | Rôle |
| --- | --- |
| `pnpm run dev` | Serveur de développement Vite |
| `pnpm run build` | Construction de production |
| `pnpm run typecheck` | Vérification TypeScript (vue-tsc) |
| `pnpm run lint` | ESLint (avec correction automatique) |
