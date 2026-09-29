# Baracod Larastarterkit

This distribution branch (`main`) contains the application skeleton. Development sources live on `dev`; validated sources live on `prod`.

After the packages have been published, create an application: `composer create-project baracod/larastarterkit my-app "1.0.*"`.

Configure `.env`, run `pnpm install`, `php artisan larastarterkit:install --no-interaction`, then `php artisan auth:super-admin:create` and `pnpm run build`.

Update shared dependencies with Composer and pnpm in a development branch. Run `larastarterkit:doctor --json`, inspect `larastarterkit:upgrade --dry-run`, test, commit lockfiles, then deploy immutable artifacts and execute `larastarterkit:upgrade --no-interaction`. Restart workers after deployment.

Local modules belong in `Modules/`. Frontend extensions belong in `resources/ts/starter.ts`. Do not edit vendor or node_modules.

Documentation is optional: install `baracod/larastarterkit-documentation` with Composer; enable it with `php artisan module:enable Documentation --no-interaction`, run the upgrade, rebuild and restart workers.

Versions 0.x were a different library installed with composer require. There is no automatic conversion of those applications. The initial 1.0 release replaces the previous repository history and removes its old tags.
