# Déploiement

Le projet conserve ses recettes Docker pour application, base SQL, Redis, workers Horizon, scheduler et gateway. Configurez les fichiers d'environnement depuis les exemples ; ne distribuez ni secrets ni données réelles.

Construisez le frontend avant le déploiement. Sur une installation vierge, exécutez migrations et catalogue, puis créez explicitement l'administrateur. Configurez mail, stockage et diffusion temps réel selon vos besoins ; les diagnostics Admin facilitent la vérification.

## Documentation publique

```sh
pnpm --dir Modules/Documentation install --frozen-lockfile
pnpm --dir Modules/Documentation run docs:dev
pnpm --dir Modules/Documentation run docs:build
```

Publiez `Modules/Documentation/docs/.vitepress/dist` comme site statique. `DOCUMENTATION_URL` règle le lien ouvert depuis Sneat. La désactivation de Documentation masque son accès dans l'application ; le site statique est administré séparément.

Les scripts de sauvegarde et de restauration sont destinés à vos futures applications. Aucune sauvegarde n'est déclenchée par l'installation du starter. Une réinitialisation explicite par `starter:restore-database` efface la base cible et demande confirmation si elle contient des tables.
