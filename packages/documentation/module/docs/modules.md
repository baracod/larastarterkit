# Créer un module

Utilisez les commandes `module:make` et les stubs existants ; `php artisan help module:make --no-interaction` décrit les options installées. Le générateur produit un CRUD complet (migration, modèle, validation, contrôleur, routes protégées, permissions, factory, test, écrans et traductions) : `larastarterkit:definition` décrit l'entité (liste de champs, table existante ou description confiée à l'IA), `larastarterkit:crud` génère, `larastarterkit` propose le menu interactif, `larastarterkit:module` crée un module. L'assistance IA (Laravel AI SDK) est facultative : `php artisan larastarterkit:ai` affiche son état. Guide complet : `vendor/baracod/larastarterkit-generator/docs/USAGE.md`.

Un module contient son manifeste `module.json`, son provider, ses routes, migrations, composants, pages et traductions. Les espaces de noms PHP suivent `Modules\Nom\` vers `Modules/Nom/app/`. Enregistrez cet autoload dans Composer, puis lancez `composer dump-autoload`.

Les pages de `resources/ts/pages` du module sont routées sous son nom en minuscules. Les alias Vite utilisent `@nom`. Les bibliothèques Vue, Pinia et i18n sont auto-importées. Utilisez ofetch, les composants Core et `t()` pour les textes visibles.

Renseignez `modules_statuses.json`, `Modules/modules.json` et le menu du module. Auth et Admin constituent le socle obligatoire. Un module optionnel doit vérifier son activation côté API avec `EnsureModuleEnabled` et ne pas être importé directement par le socle obligatoire.

Le frontend charge la disponibilité des modules via `/api/v1/modules`. Ajouter ou retirer physiquement un module nécessite de reconstruire le frontend, de régénérer l'autoload et de redémarrer les workers. Une désactivation conserve les données. Videz les caches de routes lors d'un changement de déploiement.
