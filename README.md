# Baracod Larastarterkit

This distribution branch (`main`) contains the application skeleton. Development sources live on `dev`; validated sources live on `prod`.

## Application structure

```text
app/                 Application PHP code and providers
bootstrap/           Laravel bootstrap and local cache
config/              Application configuration
database/            Application migrations, factories and seeders
Modules/             Application business modules
public/              Web entry point and public assets
resources/ts/        Vue entry point, application pages and extensions
routes/              Application routes
storage/             Local runtime data (only .gitignore files are tracked)
tests/               Application tests
```

Auth, Admin and the shared frontend are installed through Composer in
`vendor/baracod/larastarterkit-core`. They are not copied into this skeleton.
Keep the Composer, pnpm, Vite, TypeScript and PHPUnit configuration at the root.
Commit application lockfiles (`composer.lock` and `pnpm-lock.yaml`) after installation.

Dependencies, generated assets, credentials, local databases, runtime files and
release archives are ignored by Git. `packages/`, `skeleton/` and `labo/` are
also excluded on this distribution branch; these are not application directories.
Ignored files may remain on disk when switching from the development branch.

## Installation

After the packages have been published, create an application: `composer create-project baracod/larastarterkit my-app "1.0.*"`.

Configure `.env`, run `pnpm install`, `php artisan larastarterkit:install --no-interaction`, then `php artisan auth:super-admin:create` and `pnpm run build`.

## Updates and extensions

Update shared dependencies with Composer and pnpm in a development branch. Run `larastarterkit:doctor --json`, inspect `larastarterkit:upgrade --dry-run`, test, commit lockfiles, then deploy immutable artifacts and execute `larastarterkit:upgrade --no-interaction`. Restart workers after deployment.

Local modules belong in `Modules/`. Frontend extensions belong in `resources/ts/starter.ts`. Do not edit vendor or node_modules.

Documentation is optional: install `baracod/larastarterkit-documentation` with Composer; enable it with `php artisan module:enable Documentation --no-interaction`, run the upgrade, rebuild and restart workers.

Versions 0.x were a different library installed with composer require. There is no automatic conversion of those applications.

## Maintaining this distribution

Develop shared features on `dev` in `packages/`. Keep the application skeleton
in `skeleton/` on that branch aligned with changes made here before the next
release. Its development `.gitignore` must still allow package and skeleton sources.
Publish only the validated skeleton, license notices and changelog on `main`;
do not merge the full development tree into this branch.
