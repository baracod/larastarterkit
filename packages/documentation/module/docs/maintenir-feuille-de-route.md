# Maintenir : feuille de route

Objectif : qu'une équipe démarre une application sur le starter, utilise ses fonctions telles quelles, remplace ou complète tout comportement visible, développe ses modules, puis mette à jour le kit sans casse et sans jamais toucher `vendor/`.

## Principes

- **Un socle, pas un produit** : une fonction utile seulement à certaines applications devient un module optionnel ; le cœur n'apporte que le point d'extension dont ce module a besoin.
- **Auth et Admin génériques** : aucune notion métier (bibliothèque, site, client…) dans le cœur.
- **Un paquet Composer porte backend et frontend**, sans paquet npm.
- **Mises à jour explicites et sûres** : migrations additives, seeders idempotents, aucune suppression implicite.
- **Généricité prouvée** : un besoin constaté sur une application est généralisé et validé par une application témoin avant d'entrer dans le cœur.

## Livré

| Lot | Contenu |
| --- | --- |
| L1a — écrans du cœur extensibles | `defineStarter` typé, redirections configurables, remplacement de pages et de layouts, slots à plusieurs contributions dans les écrans du cœur, détection des collisions de pages, messages Auth/Admin traduisibles |
| Remplacements | Modèle utilisateur de l'application (`AUTH_MODEL`, `Larastarterkit::userModel()`), surcharge de composants, composables et stores (`resources/ts/overrides`) |

## À venir

| Lot | Contenu |
| --- | --- |
| L1b — modules autonomes | `starter.ts` par module (menus, gardes, slots, pages), fusionné par le cœur dans l'ordre des dépendances ; gardes de navigation déclarées ; contributions entre modules |
| L2 — backend remplaçable | Contrats `LoginResponse`, `AuthenticateUsing`… ; événements métier (`UserCreated`, `RolesChanged`…) ; routes du cœur activables (inscription, mot de passe oublié) ; accroches dans la vue Blade ; permissions déclarées dans `module.json` et synchronisées |
| L3 — besoins généralisés | Plusieurs publics d'utilisateurs, zone publique standard, menus avec badges, réglages de module décrits par un schéma, identité visuelle en base, thème surchargeable, code de l'application auto-importé comme un module |
| L4 — outillage et fiabilité | Commande de surcharge (`larastarterkit:override`), gabarits surchargeables, détection des surcharges obsolètes par `doctor`, démarrage Vite sans appel PHP, réglages Vite par environnement, application témoin en CI |
| L5 — enrichissement | Double authentification, sessions et jetons, politique de mot de passe, audit, santé, recherche globale… selon la demande |

Les fonctions propres à certains types d'applications (connexion OIDC, usurpation d'identité, sauvegarde, tâches planifiées, découpage par organisation, champs personnalisés) sont prévues comme **modules Composer optionnels**, appuyés sur les points d'extension du cœur.

Chaque lot se termine par une version mineure, une note de migration et sa validation sur une application pilote.
