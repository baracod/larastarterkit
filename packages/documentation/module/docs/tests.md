# Tests

PHPUnit utilise SQLite en mémoire et les migrations réelles. Les services externes sont remplacés dans les tests concernés. Pour une vérification MariaDB, utilisez exclusivement une base de test dédiée et surchargez `DB_CONNECTION` et `DB_DATABASE`.

```sh
vendor/bin/pint --dirty --format agent
vendor/bin/phpunit
pnpm run lint
pnpm run typecheck
pnpm run build
```

Utilisez `vendor/bin/phpunit` plutôt que `php artisan test`, qui transmet des options globales non prises en charge.

## Tester ses modules

- Placez les tests d'un module dans `Modules/<Nom>/tests/Feature` et `tests/Unit` ; ajoutez ces dossiers aux suites de `phpunit.xml` de l'application s'ils n'y figurent pas.
- Le générateur produit un test d'API par CRUD : authentification, permissions, création, validation et suppression groupée.
- Vérifiez chaque écran avec un administrateur et avec un utilisateur sans permission : l'API doit refuser, pas seulement l'interface.
- Une surcharge (`pages`, `resources/ts/overrides`, liaison de classe) se teste comme le reste de l'application ; relancez ces tests après chaque mise à jour du kit.
