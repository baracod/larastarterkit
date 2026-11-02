# Configuration

La configuration suit les conventions Laravel : variables dans `.env`, lues uniquement par les fichiers `config/*.php`. Dans le code, utilisez `config('…')`, jamais `env()`.

## Variables principales

| Variable | Rôle | Défaut |
| --- | --- | --- |
| `APP_NAME` | Nom affiché dans l'interface et les e-mails | — |
| `APP_LOGO` | Chemin public du logo | `/images/logos/starter.svg` |
| `APP_URL` | URL de l'application (liens, Sanctum) | — |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Langue des messages du backend (`fr`, `en`) | `en` |
| `DB_CONNECTION`, `DB_*` | Base de données (SQLite, MySQL/MariaDB) | — |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Session, cache et files d'attente | — |
| `SANCTUM_STATEFUL_DOMAINS` | Domaines autorisés à se connecter par cookie | hôte de `APP_URL` |
| `VITE_API_BASE_URL` | URL de l'API utilisée par le frontend, figée au build | `/api/v1` |
| `MAIL_*` | Envoi des e-mails (réinitialisation, notifications) | — |
| `AUTH_MAIL_CONNECTION` | Connexion de file des e-mails Auth (`auth-mail`) | `database` (`redis` si `QUEUE_CONNECTION=redis`) |
| `REVERB_*` | Temps réel (WebSocket) | — |
| `AUTH_MODEL` | Modèle utilisateur de l'application | `Modules\Auth\Models\User` |
| `DOCUMENTATION_URL` | Lien vers le site de documentation | `/docs/` |
| `GENERATOR_AI_*`, clés de fournisseurs IA | Assistance IA du générateur ([Générateur](./generateur)) | désactivée sans clé |
| `LARASTARTERKIT_STRICT_PAGES` | `true` : le build échoue sur une collision de pages | `false` |

`VITE_API_BASE_URL` est lue au moment du build : après l'avoir modifiée, reconstruisez le frontend.

## Surcharger la configuration du socle

Chaque fichier `config/*.php` du socle est fusionné avec celui de l'application : un fichier de même nom dans `config/` redéfinit ses clés de premier niveau. Les fichiers du squelette (`config/auth.php`, `config/starter.php`…) appartiennent à l'application.

## Identité visuelle

`APP_NAME` et `APP_LOGO` sont transmis au frontend par le bloc de configuration de la page. Les couleurs, la disposition (verticale ou horizontale) et les langues de l'interface relèvent du thème Sneat ; pour changer un composant d'interface, voir [Points d'extension](./extension).

## Langues

L'interface est traduite en français et en anglais. Les textes du frontend sont dans les `locales/*.json` de chaque module ; les messages du backend dans les traductions `auth::…` et `admin::…`. Pour remplacer un message du backend, créez `lang/vendor/auth/fr/messages.php` avec les seules clés à changer.
