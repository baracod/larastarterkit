# Points d'extension

Une application ne modifie jamais `vendor/`. Elle change le comportement du socle depuis `resources/ts/starter.ts` et depuis ses propres fichiers de traduction. Les éléments décrits ici constituent l'**API publique** du socle : ils restent compatibles dans toutes les versions 1.x.

## `starter.ts`

```ts
import { defineAsyncComponent } from 'vue'
import { defineStarter } from '@/extension'

export default defineStarter({
  redirects: {
    afterLogin: '/espace',
    home: user => (user ? '/espace' : '/'),
  },
  pages: {
    'auth-login': () => import('@app/views/Login.vue'),
    'root': { component: () => import('@app/views/Home.vue'), layout: 'public' },
  },
  slots: {
    'auth-login-after-form': [
      { component: defineAsyncComponent(() => import('@app/components/HelpLink.vue')), order: 10 },
    ],
  },
  menus: {},
  catalog: [],
  configure: app => {},
})
```

`defineStarter` est livré dans le paquet Composer du socle : aucun paquet npm n'est nécessaire. Avec `pnpm run typecheck`, un nom de slot, de redirection ou de route inexistant est signalé.

### Redirections

| Clé | Utilisée | Défaut |
| --- | --- | --- |
| `home` | utilisateur connecté sur une page réservée aux invités, module désactivé, fin du changement de mot de passe, logo | `/` |
| `afterLogin` | après connexion, sans paramètre `to` | `/` |
| `afterLogout` | après déconnexion | `auth-login` |
| `login` | page protégée sans session (le paramètre `to` est ajouté) | `auth-login` |
| `notAuthorized` | permission manquante | `not-authorized` |
| `mustChangePassword` | changement de mot de passe imposé | `auth-change-password` |

Chaque valeur est une route ou une fonction qui reçoit l'utilisateur courant (ou `null`).

### Remplacer une page ou un layout

`pages` associe un nom de route du socle à un composant, ou à `{ component, layout }`. Le nom, le chemin, les métadonnées (`unauthenticatedOnly`, `public`…) et les gardes de la route sont conservés.

Placez le composant **hors de `resources/ts/pages`** (par exemple `resources/ts/views`). Un fichier de `resources/ts/pages` qui a le même chemin qu'une page du socle crée une collision : le build affiche un avertissement, et l'échoue si `LARASTARTERKIT_STRICT_PAGES=true`.

Un layout de l'application portant le nom d'un layout du socle (`resources/ts/layouts/default.vue`, `blank.vue`) le remplace.

### Slots

| Slot | Écran | Contexte |
| --- | --- | --- |
| `navbar-start`, `navbar-end` | barre de navigation | — |
| `user-menu` | menu utilisateur, avant la déconnexion | `{ user }` |
| `footer` | pied de page | — |
| `auth-login-before-form`, `auth-login-after-form` | connexion | — |
| `auth-register-end` | inscription | — |
| `auth-forgot-password-after-form` | mot de passe oublié | — |
| `dashboard-start`, `dashboard-end` | tableau de bord | — |
| `profile-end` | profil utilisateur | `{ user }` |
| `users-list-actions` | liste des utilisateurs | — |

Un slot reçoit une liste de contributions `{ component, order?, when?, props? }`. Elles s'affichent après le contenu par défaut, triées par `order`. `when(context)` masque une contribution ; `props` est un objet ou une fonction du contexte. Un composant seul (forme historique) remplace le contenu par défaut.

Un module ou une application peut déclarer ses propres slots :

```ts
declare module '@/extension/define' {
  interface StarterSlotContexts {
    'catalog-record-actions': { recordId: number }
  }
}
```

## Remplacer un composant, un composable ou un store

Un fichier placé dans `resources/ts/overrides/<racine>/<chemin>` remplace le fichier du même chemin, partout où il est importé (y compris par l'auto-import des composants et composables) :

| Racine | Remplace | Exemple |
| --- | --- | --- |
| `core` | l'interface commune (`@/…`) | `overrides/core/components/CoreDataTable.vue` |
| nom du module en minuscules | les sources du module (`@auth/…`, `@admin/…`) | `overrides/auth/stores/index.ts` |

La surcharge peut importer l'original par son chemin habituel pour l'envelopper ou l'étendre :

```vue
<!-- resources/ts/overrides/auth/components/AuthAccessPanel.vue -->
<script setup lang="ts">
import OriginalPanel from '@auth/components/AuthAccessPanel.vue'

defineOptions({ inheritAttrs: false })
</script>

<template>
  <OriginalPanel v-bind="$attrs">
    <MyNotice />
    <slot />
  </OriginalPanel>
</template>
```

```ts
// resources/ts/overrides/auth/stores/index.ts
import { useAuthStore as useOriginalAuthStore } from '@auth/stores/index'

export function useAuthStore() {
  const store = useOriginalAuthStore()
  // ... comportement de l'application
  return store
}
```

Une surcharge doit garder les mêmes exports et les mêmes props que l'original : `pnpm run typecheck` vérifie le code contre les types de l'original. Un fichier qui ne correspond à aucun original est signalé au build. Le contenu des fichiers surchargés n'est pas une API publique : relisez vos surcharges à chaque mise à jour du kit. Préférez un slot, une redirection ou `pages` quand ils suffisent.

## Modèle utilisateur de l'application

Le cœur résout le modèle utilisateur avec `Larastarterkit::userModel()`, lu depuis `auth.providers.users.model` (`AUTH_MODEL`). Le modèle de l'application doit étendre `Modules\Auth\Models\User` :

```php
namespace App\Models;

class User extends \Modules\Auth\Models\User
{
    // champs, relations et méthodes de l'application
}
```

```dotenv
AUTH_MODEL=App\Models\User
```

La gestion des utilisateurs, les rôles, les commandes et les relations du cœur utilisent alors ce modèle. Le type polymorphe enregistré reste `Modules\Auth\Models\User` (alias de morph map ajouté par le cœur) : notifications et journal d'activité existants restent rattachés à vos utilisateurs.

## Remplacer une classe du backend

Les contrôleurs et services d'Auth et d'Admin sont créés par le conteneur Laravel. Une sous-classe liée dans le `register()` d'un provider de l'application les remplace :

```php
$this->app->bind(\Modules\Auth\Services\PasswordService::class, \App\Services\PasswordService::class);
```

Les noms de classes et méthodes publiques sont couverts par les règles de version ci-dessous ; le code interne d'une méthode ne l'est pas.

## Messages du backend

Les messages renvoyés par Auth et Admin passent par les traductions `auth::messages` et `admin::messages` (français et anglais), dans la langue `APP_LOCALE`. Pour en redéfinir un, créez `lang/vendor/auth/fr/messages.php` dans l'application avec seulement les clés à changer. Un dossier historique `resources/lang/modules/auth` est aussi pris en compte : ses clés remplacent celles du module une par une, sans masquer les autres.

## Versions et dépréciations

- Est public : les clés de `starter.ts`, les noms de slots et de redirections, les noms de routes du socle, les clés de traduction, les classes et contrats documentés, les commandes `larastarterkit:*`. Le reste est interne.
- Une version mineure n'introduit aucun changement cassant sur un élément public.
- Un élément public est d'abord déprécié (avertissement en développement et dans le CHANGELOG) pendant au moins une version mineure, puis retiré seulement dans une version majeure.
- Sans configuration, chaque point d'extension reproduit le comportement antérieur.
