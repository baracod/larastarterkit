<?php

namespace Baracod\Larastarterkit\Core\Services;

use Illuminate\Database\Connection;

class RuntimeSqlAccessService
{
    public function inspect(Connection $connection): array
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return ['safe_for_runtime' => false, 'reason' => 'unsupported_driver'];
        }
        $grants = $connection->select('SHOW GRANTS FOR CURRENT_USER()');
        foreach ($grants as $row) {
            $grant = (string) array_values((array) $row)[0];
            if (! preg_match('/^GRANT (.+?) ON (.+?) TO /i', $grant, $matches)) {
                return ['safe_for_runtime' => false, 'reason' => 'unrecognized_grant'];
            }
            $permissions = array_map('trim', explode(',', strtoupper($matches[1])));
            if (array_diff($permissions, ['USAGE', 'SELECT', 'INSERT', 'UPDATE', 'DELETE']) !== []
                || stripos($grant, 'WITH GRANT OPTION') !== false
                || ($matches[2] === '*.*' && $permissions !== ['USAGE'])) {
                return ['safe_for_runtime' => false, 'reason' => 'privileged_account'];
            }
        }

        return ['safe_for_runtime' => true];
    }
}
