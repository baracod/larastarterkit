# Sneat Starter — Agent Guide

Laravel 12, PHP 8.4+, Vue 3, Vuetify 3, TypeScript, Pinia, Vue Router, pnpm 9.12.2.

- Auth and Admin are mandatory modules. Documentation is optional. Documents are shared infrastructure, not a module.
- Frontend sources live in resources/ts and Modules/*/resources/ts.
- Use Composition API, ofetch, dayjs, Core components and i18n for visible UI text.
- Vue, vue-router, Pinia and vue-i18n are auto-imported.
- Permission identities use key plus action/subject; keep CASL and backend consistent.
- Module PHP namespaces map to app/ inside each module. Register Composer autoload explicitly.
- Preserve reusable components and generators. Never introduce a business-domain dependency into Auth/Admin.
- Documentation lives in Modules/Documentation/docs and uses VitePress.
- New installations only: do not run destructive migrations against an existing application database.
- Tests use PHPUnit with SQLite in memory; validate portability against a dedicated MariaDB test database.
- Use vendor/bin/pint --dirty --format agent, targeted PHPUnit tests, pnpm run lint, pnpm run typecheck, pnpm run build.
- Pass --no-interaction to Artisan commands. For PHPUnit use vendor/bin/phpunit (Artisan test forwards unsupported global flags).
- Configure services through config(), not env() outside configuration files.
- Keep document storage private, check dossier authorization, preserve previous versions.
- Adding/removing physical modules requires a frontend rebuild, autoload regeneration and worker restart.
