<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Baracod\Larastarterkit\Core\Support\StarterLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StarterDoctor extends Command
{
    protected $signature = 'larastarterkit:doctor {--json}';

    protected $description = 'Check installed backend/frontend versions and pending migrations';

    public function handle(ModuleRegistry $registry, StarterLifecycle $lifecycle): int
    {
        $errors = [];
        $versions = [];
        foreach ($registry->packages() as $name => $package) {
            $versions[$name] = $package['version'];
        }
        if (\Composer\InstalledVersions::isInstalled('baracod/larastarterkit-generator')) {
            $versions['baracod/larastarterkit-generator'] = \Composer\InstalledVersions::getPrettyVersion('baracod/larastarterkit-generator');
        }
        if ($this->callSilent('larastarterkit:frontend', ['--check' => true]) !== self::SUCCESS) {
            $errors[] = 'Frontend requirements changed: run larastarterkit:frontend, pnpm install, then build.';
        }
        try {
            $pending = $lifecycle->pending();
        } catch (Throwable $e) {
            $pending = null;
            $errors[] = 'Database unavailable; configure the connection before installation.';
        }
        $warnings = $pending === [] ? $this->setupWarnings() : [];
        $result = ['versions' => $versions, 'modules' => $registry->statuses(), 'pending_migrations' => $pending, 'errors' => $errors, 'warnings' => $warnings];
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Setup steps a new application still needs. Warnings never fail the command: upgrade runs doctor before installing.
     *
     * @return list<string>
     */
    private function setupWarnings(): array
    {
        if (! Schema::hasTable('auth_permissions') || ! Schema::hasTable('auth_user_roles')) {
            return [];
        }
        if (! DB::table('auth_permissions')->exists()) {
            return ['Starter data not initialized: run larastarterkit:install.'];
        }
        $hasAdministrator = DB::table('auth_user_roles')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->where('auth_roles.name', 'administrator')
            ->exists();

        return $hasAdministrator ? [] : ['No administrator account: run auth:super-admin:create.'];
    }
}
