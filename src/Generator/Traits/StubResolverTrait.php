<?php

namespace Baracod\Larastarterkit\Generator\Traits;

trait StubResolverTrait
{
    /**
     * Résout l'emplacement d'un stub selon la priorité :
     * 1. Stub publié par l'utilisateur
     * 2. Stub du package (Stubs/)
     * 3. Stub fallback interne dans src/
     */
    protected function resolveStubPath(string $stubName): string
    {
        // 1. Stub publié par l'utilisateur (override)
        $published = base_path("stubs/larastarterkit/{$stubName}");
        if (file_exists($published)) {
            return $published;
        }

        // 2. Stub "packagé" dans /Stubs
        $packageStub = __DIR__ . "/../../../Stubs/{$stubName}";
        if (file_exists($packageStub)) {
            return $packageStub;
        }

        // 3. Fallback interne dans src/Generator
        $internalStub = __DIR__ . "/../Stubs/{$stubName}";
        if (file_exists($internalStub)) {
            return $internalStub;
        }

        throw new \RuntimeException("Stub introuvable: {$stubName}");
    }
}
