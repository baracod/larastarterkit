<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Services\RuntimeSqlAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRuntimeSqlAccessCommand extends Command
{
    protected $signature = 'starter:runtime-access';

    protected $description = 'Refuse migration/admin privileges  on the application connection.';

    public function handle(RuntimeSqlAccessService $access): int
    {
        $result = $access->inspect(DB::connection());
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result['safe_for_runtime'] ? self::SUCCESS : self::FAILURE;
    }
}
