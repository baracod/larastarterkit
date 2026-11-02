# Auth et Admin

Auth et Admin sont les deux modules obligatoires du socle. Ils ne dépendent d'aucun module métier.

## Auth

Auth fournit la connexion par session Sanctum, la déconnexion, la récupération et le changement de mot de passe, la suspension des comptes, les profils et avatars, les rôles et les permissions. Les routes sont sous `/api/v1/auth`.

| Écran | Route |
| --- | --- |
| Connexion, mot de passe oublié, réinitialisation | `/auth/login`, `/auth/forgot-password`, `/auth/reset-password` |
| Changement de mot de passe (imposé ou volontaire) | `/auth/change-password` |
| Utilisateurs, profil | `/auth/users`, `/auth/users/{id}` |
| Rôles, permissions | `/auth/roles`, `/auth/permissions` |

Un compte peut être suspendu : il ne peut plus se connecter et ses sessions sont refusées. Un administrateur peut imposer un changement de mot de passe à la prochaine connexion.

### Super administrateur

Le rôle `administrator` possède tous les droits actuels et futurs, côté backend (`Gate::before`) comme côté interface (règle CASL `manage/all`). Les comptes administrateurs sont protégés : un utilisateur sans ce rôle ne peut pas les modifier ou les supprimer.

```sh
php artisan auth:super-admin:create --name="Administrateur" --email="admin@example.test"
php artisan auth:super-admin:password --email="admin@example.test"
```

Le détail des options est dans [Installation](./installation).

## Rôles et permissions

Une permission possède une clé et un couple `action` / `subject`. Les actions usuelles sont `browse`, `read`, `add`, `edit` et `delete` ; le sujet est en général le nom de la table ou de la ressource (`auth_users`). Les permissions sont regroupées dans des rôles attribués aux utilisateurs.

**Protéger une route** avec les middlewares déclarés dans `bootstrap/app.php` :

```php
Route::middleware(['auth:sanctum', 'active', 'must_change_pass'])->prefix('v1/shop')->group(function () {
    Route::get('products', [ProductController::class, 'index'])->middleware('ability:browse,shop_products');
    Route::post('products', [ProductController::class, 'store'])->middleware('ability:add,shop_products');
});
```

| Middleware | Effet |
| --- | --- |
| `auth:sanctum` | Session authentifiée |
| `active` | Refuse un compte suspendu (423) |
| `must_change_pass` | Bloque tant qu'un changement de mot de passe est imposé |
| `ability:action,subject` | Exige la permission correspondante |
| `administrator` | Réservé au rôle administrateur |

**Dans l'interface**, utilisez le même couple :

```vue
<VBtn v-if="$can('add', 'shop_products')">
  {{ t('action.add') }}
</VBtn>
```

Les pages déclarent leur permission par `definePage({ meta: { action: 'browse', subject: 'shop_products' } })` ; le routeur refuse la navigation sans permission. Les gardes frontend sont un confort : l'autorisation réelle reste côté API.

**Déclarer les permissions d'un module** : dans un seeder de permissions idempotent (le générateur produit `GeneratedPermissionSeeder`), référencé dans `upgradeSeeders` de `module.json` pour être appliqué par `larastarterkit:upgrade`. Les permissions créées sont rattachées au rôle `administrator`.

## Admin

Admin regroupe les paramètres système, les paramètres des modules et des utilisateurs, la configuration des notifications, le catalogue des modules (activation et désactivation) et les diagnostics de connexion. Ses opérations administratives requièrent le rôle administrateur.

| Écran | Route |
| --- | --- |
| Paramètres | `/admin/settings`, `/admin/adminsettings` |
| Modules | `/admin/modules` |
| Notifications | `/admin/notifications` |
| Diagnostics des connexions | `/admin/connections` |
| Circuit des documents | `/admin/document-workflow` |

### Paramètres utilisateur par module

`GET` et `PUT /api/v1/auth/users/{id}/modules` lisent et écrivent les préférences d'un utilisateur pour un module :

```json
{"settings":[{"module":"Admin","key":"dashboard","value":{"view":"overview"}}]}
```

La lecture est réservée au propriétaire et aux administrateurs, l'écriture aux administrateurs. Les entrées envoyées sont ajoutées ou mises à jour, les autres sont conservées. Ces préférences n'accordent pas de permissions.

### Notifications

Les notifications peuvent être envoyées par e-mail, enregistrées en base et diffusées en temps réel (Reverb). Les e-mails Auth passent par la file `auth-mail` : démarrez un worker (`php artisan queue:work --queue=auth-mail`) ou Horizon en production.

## Personnaliser Auth

Redirections, page de connexion, éléments ajoutés aux écrans, modèle utilisateur, messages et classes du backend se personnalisent sans modifier le socle : voir [Points d'extension](./extension).
