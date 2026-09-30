# Changelog

## 1.0.0-rc.4

Version unique pour tous les packages : core, generator et squelette sont désormais publiés ensemble depuis le monorepo.

### Corrections

- Connexion possible quel que soit le port ou l'`APP_URL` (hôte courant accepté par Sanctum) ; erreur 419 explicite au lieu d'une erreur 500 sans session.
- Création d'un utilisateur sans rôle : plus d'erreur, liste de rôles vide acceptée.
- Routes API `create`/`edit` supprimées (`apiResource`).
- Boutons « Ajouter » (utilisateurs, rôles, permissions, paramètres) : formulaire vide, « Actif » coché par défaut.
- Traductions `world` (pays) de nouveau incluses dans le package core.
- Squelette : dossiers `storage/logs`, `storage/framework/testing` et `database/migrations` conservés.
- Génération des icônes et du `tsconfig` dans `.larastarterkit/` (plus d'écriture dans `vendor/`).

### Interface

- Accueil : modules présentés en cartes.
- Traductions complétées (menu utilisateur, notifications, langues, onglets, messages de validation).
- Titre de l'application tronqué proprement ; bouton de repli du menu circulaire.
- Pages de démonstration retirées.

### Outils

- `larastarterkit:doctor` signale l'installation ou l'administrateur manquant.
- `composer create-project` affiche les étapes suivantes.

## 1.0.0-rc.1

- Squelette `composer create-project`, avec core et generator installés par défaut.
- Auth/Admin et infrastructure Documents dans le socle Composer ; Documentation facultative.
- Frontend livré avec chaque package Composer, découverte Vite des modules installés et locaux.
- Commandes install, doctor, upgrade, frontend et préparation de modules distribuables.
- Personnalisation applicative via contributions de navigation, slots et initialisation Vue.
- Organisation en branches `dev` et `prod`, sans scripts personnalisés de livraison ni configurations locales d’assistants.

### Transition depuis 0.x

La génération 1.0 vise les nouvelles applications. Les installations historiques par `composer require baracod/larastarterkit` conservent leurs versions et ne migrent pas automatiquement. Les mises à jour d'une application 1.x portent sur ses dépendances Composer et son verrouillage pnpm ; le squelette n'est pas réinstallé.
