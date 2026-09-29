# Changelog

## 1.0.0-rc.1 — en préparation

- Squelette `composer create-project`, avec core et generator installés par défaut.
- Auth/Admin et infrastructure Documents dans le socle Composer ; Documentation facultative.
- Frontend livré avec chaque package Composer, découverte Vite des modules installés et locaux.
- Commandes install, doctor, upgrade, frontend et préparation de modules distribuables.
- Personnalisation applicative via contributions de navigation, slots et initialisation Vue.
- Organisation en branches `dev` et `prod`, sans scripts personnalisés de livraison ni configurations locales d’assistants.

### Transition depuis 0.x

La génération 1.0 vise les nouvelles applications. Les installations historiques par `composer require baracod/larastarterkit` conservent leurs versions et ne migrent pas automatiquement. Les mises à jour d'une application 1.x portent sur ses dépendances Composer et son verrouillage pnpm ; le squelette n'est pas réinstallé.
