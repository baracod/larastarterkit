# Dépannage

Commencez toujours par `php artisan larastarterkit:doctor --no-interaction` : il signale les migrations en attente, les incohérences de versions entre backend et frontend, et l'installation ou l'administrateur manquants.

| Symptôme | Cause probable | Solution |
| --- | --- | --- |
| Page blanche, aucune erreur | Le frontend appelle l'API sur une autre URL que celle du serveur | Vérifiez `VITE_API_BASE_URL` (figée au build) et `APP_URL`, puis reconstruisez |
| Connexion refusée avec une erreur 419 | L'origine de la page n'est pas un domaine Sanctum « stateful » | Ajoutez l'hôte (avec le port) à `SANCTUM_STATEFUL_DOMAINS` |
| Réponse 423 à chaque requête | Compte suspendu | Réactivez le compte dans Auth → Utilisateurs |
| Redirection permanente vers le changement de mot de passe | Changement imposé par un administrateur | Changez le mot de passe ; un administrateur peut lever l'obligation |
| Nouveau module invisible | Autoload, frontend ou workers pas à jour | `composer dump-autoload`, `pnpm run build`, `php artisan queue:restart` |
| Écran généré absent | Frontend non reconstruit | `pnpm run build` ou `pnpm run dev` |
| Classe introuvable après création d'un module | Autoload non régénéré | `composer dump-autoload` |
| E-mails non envoyés | Aucun worker sur la file `auth-mail` | `php artisan queue:work --queue=auth-mail` ou Horizon |
| Message affiché sous forme de clé (`auth::messages.…`) | Traductions de l'application incomplètes | Vérifiez `lang/vendor/auth/<langue>` ; les clés absentes viennent du socle |
| Avertissement « Page … is defined by … » au build | Deux pages pour la même URL | Déclarez la surcharge dans `pages` de `starter.ts` et déplacez le composant hors de `resources/ts/pages` |
| Avertissement « Override … matches no file » | Fichier de `resources/ts/overrides` sans original | Corrigez le chemin (`core/…` ou nom du module en minuscules) |
| Le build affiche encore l'ancienne surcharge en développement | Fichier de surcharge ajouté ou supprimé | Vite redémarre seul ; sinon relancez `pnpm run dev` |
| `The user model [...] must extend ...` | `AUTH_MODEL` désigne un modèle qui n'étend pas celui du socle | Faites étendre `Modules\Auth\Models\User` |

## Mise à jour qui échoue

`larastarterkit:upgrade` s'arrête avant les seeders si une migration échoue. Corrigez la cause, puis relancez : les migrations terminées sont conservées et les seeders sont idempotents. Les contraintes JavaScript divergentes entre modules sont refusées explicitement : alignez les `package.json` des modules.
