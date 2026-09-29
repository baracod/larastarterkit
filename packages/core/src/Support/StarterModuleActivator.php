<?php

namespace Baracod\Larastarterkit\Core\Support;

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Module;

class StarterModuleActivator extends FileActivator
{
    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        return (app(ModuleRegistry::class)->statuses()[$name] ?? false) === $status;
    }

    public function setActiveByName(string $name, bool $status): void
    {
        if (! $status && in_array($name, ['Auth', 'Admin'], true)) {
            throw new \LogicException('Auth and Admin are required modules.');
        }
        $statuses = app(ModuleRegistry::class)->statuses();
        $statuses[$name] = $status;
        app(ModuleRegistry::class)->statuses($statuses);
        parent::setActiveByName($name, $status);
    }
}
