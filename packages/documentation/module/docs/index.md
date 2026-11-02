# Larastarterkit

`baracod/larastarterkit` est un socle Laravel 12 + Vue 3 + Vuetify + TypeScript pour construire des applications modulaires : authentification, rôles et permissions, administration, interface commune, générateur CRUD et outils documentaires sont prêts dès l'installation. L'application ajoute ses propres modules et personnalise le socle sans jamais modifier `vendor/`.

## Deux parcours

**Vous construisez une application avec le starter**

1. [Concepts](./concepts) : ce que contient le starter et comment les pièces s'assemblent.
2. [Installation](./installation), puis [Configuration](./configuration).
3. [Auth et Admin](./auth-admin) : comptes, rôles, permissions, paramètres.
4. [Créer un module](./modules) et [Générateur CRUD](./generateur).
5. [Points d'extension](./extension) : changer un écran, une redirection, une classe, un composant.
6. [Composants](./components), [Documents](./documents), [CMS de documentation](./cms).
7. [Mettre à jour](./distribution), [Tests](./tests), [Déploiement](./deployment).
8. [Commandes](./commandes) et [Dépannage](./depannage).

**Vous maintenez le starter lui-même**

1. [Le dépôt](./maintenir-depot) : monorepo, branches, environnement de travail.
2. [Architecture interne](./maintenir-architecture) : démarrage backend, construction du frontend, registre des modules.
3. [API publique et versions](./maintenir-api-publique) : ce qui doit rester stable et comment le faire évoluer.
4. [Qualité et tests](./maintenir-qualite) : vérifications locales et CI.
5. [Publier une version](./maintenir-publication).
6. [Feuille de route](./maintenir-feuille-de-route).

## En bref

| Élément | Rôle |
| --- | --- |
| `baracod/larastarterkit` | Squelette installé par `composer create-project` |
| `baracod/larastarterkit-core` | Infrastructure, modules Auth et Admin, interface commune, outils documentaires |
| `baracod/larastarterkit-generator` | Générateurs de modules et de CRUD (dépendance de développement) |
| `baracod/larastarterkit-documentation` | Module Documentation (CMS + site VitePress), facultatif |

Auth et Admin sont obligatoires. Documentation est optionnel. Les documents (import, génération PDF, signatures, historique) sont une infrastructure partagée, pas un module.
