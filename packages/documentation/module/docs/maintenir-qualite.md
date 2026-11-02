# Maintenir : qualité et tests

## Avant chaque commit

```sh
vendor/bin/pint --dirty --format agent
vendor/bin/phpunit
node --import tsx --test tests/frontend/*.test.ts
pnpm run lint
pnpm run typecheck
pnpm run build
```

- Utilisez `vendor/bin/phpunit` et non `php artisan test` (options globales non prises en charge).
- `pnpm run lint` corrige automatiquement **tout** le dépôt ; pour limiter aux fichiers touchés : `pnpm exec eslint -c .eslintrc.cjs --ext .ts,.vue <fichiers>`.
- Pour une page de documentation : `pnpm --dir Modules/Documentation run docs:build`.

## Tests PHP

PHPUnit utilise SQLite en mémoire et les migrations réelles (`tests/Unit`, `tests/Feature`, `Modules/*/tests`). Validez la portabilité sur une base MariaDB de test dédiée :

```sh
DB_CONNECTION=mysql DB_DATABASE=starter_test vendor/bin/phpunit
```

Ne pointez jamais les tests vers une base de développement ou de production.

Les tests de régression du dépôt sont dans `tests/Feature` ; ceux propres à un module dans `packages/*/modules/<Module>/tests`. Les classes d'appoint (par exemple un modèle utilisateur d'application) vont dans `tests/Fixtures`.

## Tests frontend

`tests/frontend/*.test.ts` utilise `node:test` exécuté par tsx, sans navigateur. On y teste la logique pure : extension (`extension/core.ts`), plugins Vite (`vite/modules.ts`), stores et API avec leurs dépendances remplacées. Gardez la logique testable hors des fichiers qui importent `virtual:larastarterkit`.

Pour un changement visible, vérifiez aussi dans un navigateur : connexion, navigation et écran modifié, avec un administrateur et avec un utilisateur sans permission.

## Intégration continue

Le workflow `.github/workflows/production-checks.yml` s’exécute sur les pushes et pull requests vers `dev` et `prod` : installation, build, ESLint, typecheck, PHPUnit sur MariaDB puis sur SQLite, build de la documentation, `composer audit`, `pnpm audit --prod` et construction des images de production.

## Conventions

- PHP formaté par Pint ; TypeScript et Vue par ESLint (configuration du dépôt).
- Imitez le code voisin : nommage, densité de commentaires, idiomes.
- Commentaires utiles seulement : le pourquoi, pas le quoi.
- Messages de commit au format `type: description` (`feat`, `fix`, `refactor`, `docs`, `ci`…).
