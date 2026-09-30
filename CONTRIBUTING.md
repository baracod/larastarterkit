# Contribuer à Baracod Larastarterkit

Ce dépôt contient l'application de référence et les sources des packages. Le squelette publié avec `composer create-project` est dans `skeleton/`.

| Répertoire | Responsabilité |
| --- | --- |
| `packages/core/` | Infrastructure, modules Auth/Admin et interface commune |
| `packages/generator/` | Générateurs et stubs |
| `packages/documentation/` | CMS et publication VitePress, backend et frontend |
| `skeleton/` | Fichiers propres à une nouvelle application |
| `tests/` | Tests d'intégration et de régression du dépôt de développement |
| `docker/` | Développement et déploiement de l'application de référence |

Les liens `Modules/`, `resources/` et `stubs` permettent de travailler sur les sources des packages depuis l'application de référence. Le squelette distribué charge ces sources depuis les dépendances Composer.

Installer avec `composer install`, configurer `.env`, puis exécuter `pnpm install`. Utiliser une base de développement dédiée et suivre le README pour l'initialisation. Ne pas versionner de secrets, dumps, caches, dépendances installées ou rapports personnels.

Placer migrations, seeders, traductions et frontend dans le package ou module responsable. Les personnalisations d'une application restent dans cette application ; une mise à jour ne doit pas les réécrire. Préserver les contrats de permissions Laravel/CASL.

Avant de proposer un changement :

```sh
vendor/bin/pint --dirty --format agent
vendor/bin/phpunit
pnpm run lint
pnpm run build
pnpm run typecheck
```


## Branches

Développer sur `dev`, exécuter les vérifications ci-dessus, puis ouvrir une pull request de `dev` vers `prod`. `prod` représente le code stable ; aucun déploiement automatique n’est configuré. Les branches distantes et leurs protections sont à configurer sur GitHub.

Ce dépôt est un monorepo : `packages/core`, `packages/generator` et `skeleton/` ne sont développés qu’ici. Les packages n’ont pas de dépôt Git local ; leurs dépôts GitHub (`larastarterkit-core`, `larastarterkit-generator`) sont des miroirs en lecture seule, alimentés par le workflow `.github/workflows/split.yml`. Ne pas y pousser directement. `packages/documentation` n’est pas encore publié.

- Chaque push sur `prod` recopie core et generator sur la branche `main` de leur dépôt.
- Fusionner `dev` dans `prod` ne crée aucune version Packagist.

### Publier une version

Tous les packages partagent une seule version.

1. Sur `dev` : `php bin/set-version.php 1.0.0-rc.4`, puis `composer update "baracod/*" --no-interaction`, mettre à jour `CHANGELOG.md`, commiter et fusionner dans `prod`.
2. Sur `prod` à jour, créer et pousser le tag de publication : `git tag release/1.0.0-rc.4` puis `git push origin release/1.0.0-rc.4`. Le workflow « Split and release packages » vérifie que le commit appartient à `prod` et que `composer.json` porte cette version, tague core et generator `1.0.0-rc.4`, puis publie `skeleton/` sur `main` avec le même tag.

Le lancement manuel (« Run workflow ») n’est pas utilisé : GitHub l’exige sur la branche par défaut, `main`, qui ne contient que le squelette. Les tags `release/*` ne sont pas des numéros de version et sont ignorés par Packagist.

Le workflow utilise le secret `SPLIT_ACCESS_TOKEN` : un jeton GitHub ayant les droits d’écriture (contents) sur `larastarterkit`, `larastarterkit-core` et `larastarterkit-generator`.

### Branche de distribution

`main` contient le contenu de `skeleton/`, accompagné de `LICENSE`, `THIRD_PARTY_NOTICES.md` et `CHANGELOG.md`. Son historique est indépendant et n’est alimenté que par le workflow de publication : ne jamais y fusionner `dev` ou `prod`. Packagist lit `baracod/larastarterkit` depuis cette branche et ses tags.

`main` est destinée à devenir la branche par défaut du dépôt GitHub. `dev` conserve les sources complètes et `prod` les sources validées.
