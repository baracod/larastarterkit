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

La publication Composer reste manuelle : les packages et le squelette doivent être distribués depuis leurs répertoires respectifs. Fusionner `dev` dans `prod` ne publie pas à lui seul une version Packagist. Les modules, leur frontend et le générateur restent inchangés.

### Branche de distribution

`main` contient le contenu de `skeleton/`, accompagné de `LICENSE`, `THIRD_PARTY_NOTICES.md` et `CHANGELOG.md`. Son historique est indépendant. Ne pas fusionner directement `prod` dans `main` : reporter le squelette validé sur `main` par un commit normal, puis y créer le tag de version. Publier core et generator dans leurs dépôts Composer avant la version du squelette qui les référence. Les publications suivantes conservent cet historique.

`main` est destinée à devenir la branche par défaut du dépôt GitHub. `dev` conserve les sources complètes et `prod` les sources validées.
