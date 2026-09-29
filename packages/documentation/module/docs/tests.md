# Tests

PHPUnit utilise SQLite en mémoire et les migrations réelles. Les services externes sont remplacés dans les tests concernés. Pour une vérification MariaDB, utilisez exclusivement une base de test dédiée et surchargez `DB_CONNECTION` et `DB_DATABASE`.

```sh
vendor/bin/pint --dirty --format agent
vendor/bin/phpunit
pnpm run lint
pnpm run typecheck
pnpm run build
pnpm --dir Modules/Documentation run docs:build
```

Les tests couvrent installation, seeding idempotent, Auth/Admin, documents et activation des modules. Vérifiez également les pages avec un administrateur et un utilisateur sans permissions.
