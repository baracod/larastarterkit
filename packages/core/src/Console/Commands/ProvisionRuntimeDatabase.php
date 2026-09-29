<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Services\RuntimeSqlAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProvisionRuntimeDatabase extends Command
{
    protected $signature = 'starter:runtime-database {username} {--host=%} {--password-file=}';

    protected $description = 'Create or extend a restricted runtime SQL account from a separate maintenance connection.';

    public function handle(RuntimeSqlAccessService $access): int
    {
        $username = (string) $this->argument('username');
        $host = (string) $this->option('host');
        $path = (string) $this->option('password-file');
        if (! preg_match('/^[a-zA-Z0-9_]{1,32}$/', $username)
            || ! preg_match('/^[a-zA-Z0-9_.%:-]+$/', $host)
            || ! is_readable($path)) {
            $this->error('Provide a SQL username, host and readable password file.');

            return self::FAILURE;
        }
        $password = rtrim(file_get_contents($path), "\r\n");
        if (strlen($password) < 20) {
            $this->error('The runtime SQL password must contain at least 20 characters.');

            return self::FAILURE;
        }

        $runtime = null;
        try {
            $admin = DB::connection();
            if (! in_array($admin->getDriverName(), ['mysql', 'mariadb'], true)
                || $username === $admin->getConfig('username')) {
                throw new RuntimeException('Separate maintenance account required.');
            }
            $exists = $admin->table('mysql.user')->where('User', $username)->where('Host', $host)->exists();
            $pdo = $admin->getPdo();
            $account = $pdo->quote($username).'@'.$pdo->quote($host);
            if (! $exists) {
                // Keep password-bearing SQL out of Laravel's query logger.
                $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password));
            }
            $runtime = DB::build([...$admin->getConfig(), 'name' => null, 'url' => null, 'database' => null, 'username' => $username, 'password' => $password]);
            $runtime->setDatabaseName($admin->getDatabaseName());
            $identity = $runtime->selectOne('SELECT CURRENT_USER() AS account')->account;
            if ($identity !== $username.'@'.$host || ! $access->inspect($runtime)['safe_for_runtime']) {
                throw new RuntimeException('Existing account is privileged or resolves to another host.');
            }

            $quoteIdentifier = fn (string $value): string => '`'.str_replace('`', '``', $value).'`';
            $database = $admin->getDatabaseName();
            $tables = $admin->table('information_schema.TABLES')->where('TABLE_SCHEMA', $database)->get(['TABLE_NAME', 'TABLE_TYPE']);
            foreach ($tables as $table) {
                $name = $table->TABLE_NAME;
                $privileges = $table->TABLE_TYPE === 'VIEW' || $name === 'migrations' ? 'SELECT' : 'SELECT, INSERT, UPDATE, DELETE';
                $admin->statement('GRANT '.$privileges.' ON '.$quoteIdentifier($database).'.'.$quoteIdentifier($name).' TO '.$account);
            }
            $runtime->statement('USE '.$quoteIdentifier($database));
            if (! $access->inspect($runtime)['safe_for_runtime']) {
                throw new RuntimeException('Runtime verification failed.');
            }
            $this->info('Runtime SQL account verified; table grants applied: '.$tables->count().'.');

            return self::SUCCESS;
        } catch (Throwable) {
            // SQL exceptions can include credentials; keep maintenance logs secret-free.
            $this->error('Provisioning failed. Check maintenance privileges, the runtime credentials. Existing privileged accounts are refused; use a new dedicated runtime account.');

            return self::FAILURE;
        } finally {
            $runtime?->disconnect();
        }
    }
}
