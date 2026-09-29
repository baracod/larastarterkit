# CMS Documentation

Le module Documentation gère plusieurs documentations et éditions. Les pages, révisions et instantanés publiés sont enregistrés en base. Le site VitePress est public ; le CMS, les aperçus et les images originales nécessitent une connexion et les permissions correspondantes.

## Installation et démarrage

```bash
php artisan migrate --force --no-interaction
php artisan module:seed Documentation --force --no-interaction
pnpm --dir Modules/Documentation install --frozen-lockfile
php artisan documentation:import --no-interaction
php artisan queue:work documentation --queue=documentation --timeout=300 --tries=1 --no-interaction
```

L'import crée « Sneat Starter / v1 » depuis les Markdown existants. Il ne remplace aucune page connue, même renommée ou archivée, et ne publie rien. Les seeders créent seulement les permissions. Les migrations ne suppriment pas les données existantes.

Ouvrez le module Documentation dans l'application. Créez ou sélectionnez une documentation et une édition. Une nouvelle édition peut être vide ou dupliquer les pages d'une édition existante de la même documentation.

## Espace de gestion

Le module s'ouvre sur `/documentation`, un tableau de bord indiquant les documentations disponibles, les éditions, les pages et les médias du contexte courant, ainsi que les dernières modifications et publications.

La navigation propose six pages : Tableau de bord, Documentations, Rédaction, Médiathèque, Publications et Configurations. La page Documentations permet de créer les documentations, de gérer leurs éditions et leurs archives. La page Configurations regroupe les réglages de l'accueil public.

Le sélecteur de la barre supérieure choisit la documentation et l'édition courantes. Le contexte est conservé dans les liens du module et au rechargement de la page. Un changement de contexte ou de page demande confirmation si une rédaction n'a pas été enregistrée. Une édition appartenant à une autre documentation ne peut pas être sélectionnée par l'URL.

L'espace Rédaction conserve l'arborescence des pages, l'éditeur visuel/Markdown, l'aperçu, l'insertion des images et les révisions. Le mode concentration agrandit cet espace ; Échap permet de revenir à la navigation normale.

## Rédaction

Enregistrez les pages en Markdown : chaque sauvegarde crée une révision avec son auteur et sa date. L'arborescence se règle par le parent et l'ordre. Le chemin d'une page est indépendant de son parent. Les chemins sont uniques par édition ; `index`, `assets` et `404` sont réservés.

L'aperçu applique les mêmes règles que la publication. Le HTML brut est supprimé, les liens exécutables sont neutralisés et le Markdown n'est jamais compilé comme du code Vue. Les blocs de code restent littéraux. Les diagrammes Mermaid sont rendus en mode strict, sans directives de configuration éditoriales.

La bibliothèque accepte PNG, JPEG et WebP, 10 Mo maximum par défaut (`DOCUMENTATION_IMAGE_MAX_KB`). Insérez une image avec le bouton prévu ou `![Description](doc-image:123)`. Les images restent privées avant publication ; seules celles référencées par l'instantané sont exportées. Les images externes liées par URL restent gérées par leur hébergeur.

Une sauvegarde concurrente est refusée avec HTTP 409, sans écraser les données ni les saisies de l'utilisateur. Rechargez la page concernée après avoir conservé vos modifications. Restaurer une révision en crée une nouvelle ; l'historique est conservé.

## Éditeur visuel et Markdown

La rédaction propose deux modes : **Visuel** et **Markdown**. Le Markdown reste le format enregistré dans les révisions. Passer d'un mode à l'autre ne sauvegarde rien automatiquement. La barre visuelle permet de choisir les titres, le gras, l'italique, les listes, les tâches, les citations, les tableaux et les liens. Annuler et rétablir restent disponibles pendant la rédaction.

Tapez `/` dans un paragraphe vide pour ouvrir le menu des blocs. Les boutons Code et Mermaid ajoutent des blocs avec leur langage ; sélectionnez un bloc pour modifier le langage. Un diagramme s'écrit dans le bloc Mermaid et se vérifie dans l'onglet Aperçu. Le bouton Image ouvre la bibliothèque et insère le fichier à la position du curseur. Le rendu public et l'aperçu conservent les mêmes restrictions de sécurité.

Les modes visuels couvrent le Markdown courant (titres, listes, liens, tableaux, code et images). Les constructions spécifiques à d'autres moteurs Markdown ne deviennent pas des composants exécutables.

## Accueil et apparence

La page **Configurations** configure le portail commun à toutes les documentations : nom, logo, titre de la bannière, slogan, illustration, jusqu'à quatre boutons, douze cartes de présentation, six liens de navigation, lien GitHub et pied de page. Les cartes peuvent avoir une icône emoji et un lien. Les images sont sélectionnées dans la bibliothèque de la documentation courante ; les réglages déjà enregistrés conservent leurs images même lorsqu'on sélectionne une autre documentation.

Les liens internes commencent à la racine du portail, par exemple `/sneat-starter/v1/installation.html` (sans le préfixe `/docs`). Les liens externes doivent utiliser HTTP(S). L'aperçu dans le formulaire permet de vérifier les textes avant publication.

Enregistrez les réglages, puis publiez une édition pour les appliquer au site public. Les réglages d'accueil et les images choisies font partie de l'instantané : une restauration de publication les rétablit avec le contenu, sans effacer les réglages encore en brouillon. Une modification concurrente des réglages renvoie HTTP 409.

Le portail propose les thèmes clair et sombre, un sommaire d'article à droite, une arborescence repliable à gauche, les pages précédente/suivante, la coloration et la copie des blocs de code. `Ctrl K` (ou `⌘ K`) ouvre la recherche, limitée à l'édition consultée ; depuis l'accueil, la recherche couvre le catalogue public.

## Publication et restauration

Le bouton Publier capture immédiatement les pages enregistrées et les images de l'édition. Les modifications suivantes restent en brouillon. La file `documentation` construit le portail et le remplace atomiquement uniquement après réussite. Les autres éditions déjà publiées sont conservées. Les identifiants sont bloqués pendant une construction et deviennent définitifs après la première publication réussie. Une construction initiale échouée permet de les modifier.

La recherche et le sélecteur de version sont propres à la documentation et à l'édition courantes. `/docs/` affiche le catalogue, puis `/docs/{documentation}/{edition}/{page}.html` expose chaque page. L'édition par défaut apparaît en premier dans le catalogue lors de sa prochaine publication.

L'historique des publications affiche leur état et leur journal. Un échec conserve le site précédent. Corrigez la cause puis publiez de nouveau. Restaurer une publication remet **tout le portail** à cet instantané, sans modifier les brouillons. Les publications et fichiers historiques sont conservés ; aucun nettoyage automatique destructif n'est lancé.

L'archivage conserve les révisions et fichiers. Il masque les contenus dans le CMS par défaut. L'archivage d'une page est reflété dans la prochaine publication de son édition. Pour retirer une édition ou documentation archivée du site public, affichez les archives puis publiez ; les retraits sont capturés dans l’instantané suivant. Désactiver le module bloque les API du CMS et les nouvelles constructions ; le dernier site public reste disponible.

## Permissions

Les droits CMS utilisent le sujet `documentation` et les actions `browse`, `edit`, `media`, `publish`, `restore`. `browse` est nécessaire pour entrer dans le CMS ; restaurer une page requiert aussi `edit`, restaurer le portail requiert aussi `publish`. Attribuez ces permissions aux rôles existants. Le super administrateur les possède toutes. `access_documentation` reste la permission historique publique, mais ne donne aucun accès aux brouillons.

## Production

Le worker dédié doit disposer de PHP, Node et des dépendances VitePress verrouillées. La connexion `documentation` utilise la base de données, une réservation de 600 secondes et un délai de construction plafonné à 240 secondes ; le worker expire après 300 secondes. Il ne dépend pas de Redis et ne doit pas être remplacé par un worker Horizon générique.

Deux cibles Docker sont fournies : `dokploy-documentation-builder` et `dokploy-documentation-cms`. Le fichier `docker-compose.documentation.yml` les relie au volume Laravel et au réseau existants, désignés par `DOCUMENTATION_STORAGE_VOLUME` et `DOCUMENTATION_NETWORK`. Copiez votre configuration Laravel dans `docker/env/app.env`. Le worker nécessite les accès SQL du CMS ; le serveur statique monte seulement le stockage en lecture seule. Configurez `DOCUMENTATION_URL` dans l'application vers l'URL publique se terminant par `/docs/`.

Les originaux et espaces de construction sont sous `storage/app/documentation`, hors du répertoire public. Seul `site/current` est exposé. En local, la première publication remplace `public/docs` par un lien vers ce répertoire après conservation du site précédent. Sur l'image dédiée, `DOCUMENTATION_PUBLIC_LINK=false` laisse Nginx servir directement le volume partagé. Ne lancez plus `docs:build:local` sur un lien de publication CMS : cette commande concerne uniquement le site statique historique.

La construction Node reçoit un environnement filtré sans secrets Laravel. Elle réutilise les dépendances installées et ne télécharge rien à la publication. La sérialisation utilise un verrou de fichier commun : tous les workers doivent partager le même volume POSIX. Redémarrez les workers après déploiement avec `php artisan queue:restart --no-interaction`.

Les sauvegardes doivent couvrir la base et `storage/app/documentation` ensemble. Le journal de chaque publication est consultable depuis le CMS ; surveillez aussi les échecs de jobs Laravel et l'espace disque utilisé par les révisions et publications conservées.

En cas d’arrêt brutal du worker, `documentation:recover --no-interaction` remet en file les demandes inactives depuis dix minutes, sous le même verrou de publication. Le scheduler l’exécute toutes les cinq minutes ; la commande peut aussi être lancée manuellement. Les livraisons répétées sont idempotentes.
