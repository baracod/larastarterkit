# Auth et Admin

Auth fournit connexion Sanctum, déconnexion, récupération et changement de mot de passe, suspension, profils et avatars, rôles et permissions. Les routes restent sous `/api/v1/auth`.

Les permissions utilisent une clé `key` et le couple `action` / `subject`, partagé avec CASL. Le rôle `administrator` conserve les accès administratifs. Les notifications peuvent être envoyées par mail, enregistrées en base et diffusées en temps réel.

Admin regroupe paramètres système, module et utilisateur, configuration des notifications, catalogue des modules et diagnostics. Ses opérations administratives requièrent le rôle administrateur.

Les paramètres utilisateur par module passent par `GET` et `PUT /api/v1/auth/users/{id}/modules`. Exemple de corps :

```json
{"settings":[{"module":"Admin","key":"dashboard","value":{"view":"overview"}}]}
```

La lecture est réservée au propriétaire et aux administrateurs. L'écriture est administrative. Les entrées envoyées sont ajoutées ou mises à jour ; les autres entrées restent conservées. Ces préférences n'accordent pas de permissions.
