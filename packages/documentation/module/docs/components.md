# Composants réutilisables

Le répertoire `resources/ts/@core` contient les briques Sneat ; `resources/ts/components` contient les composants partagés de l'application.

Réutilisez CoreDataTable, CoreFilterPanel, CoreDialog, CoreNotify, les champs Core, les lecteurs PDF, SignaturePad et les composants graphiques. Les bibliothèques de cartes, GPS et QR restent disponibles.

CoreJourneyNav reçoit une liste `items` avec `title`, `to` et éventuellement `icon` ; il ne dépend d'aucun parcours métier. Les composants documentaires partagés se trouvent dans `resources/ts/components`.

Les composants de démonstration ne constituent pas automatiquement des fonctions backend : raccordez leurs actions à vos API et politiques.

Personnalisez le nom et le logo via `APP_NAME` et `APP_LOGO`. Les réglages de thèmes, dispositions, langues et couleurs restent dans la configuration Sneat.
