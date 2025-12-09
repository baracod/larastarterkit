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
        // cache static pour éviter re-scans
        static $cache = [];

        // normaliser le nom du stub (trim slashes)
        $stubName = ltrim(str_replace('\\', '/', $stubName), '/');

        if (isset($cache[$stubName])) {
            return $cache[$stubName];
        }

        // 1) stub publié par l'utilisateur (override)
        $published = base_path("stubs/larastarterkit/{$stubName}");
        if (file_exists($published)) {
            return $cache[$stubName] = $published;
        }

<<<<<<< HEAD
        // 2) stub spécifique au "laravel-module" personnalisé dans Stubs/laravel-module/
        $packageLaravelModuleStub = realpath(__DIR__."/../../../Stubs/laravel-module/{$stubName}");
        if ($packageLaravelModuleStub && file_exists($packageLaravelModuleStub)) {
            return $cache[$stubName] = $packageLaravelModuleStub;
        }

        // 3) stub packagé générique dans /Stubs
        $packageStub = realpath(__DIR__."/../../../Stubs/{$stubName}");
        if ($packageStub && file_exists($packageStub)) {
            return $cache[$stubName] = $packageStub;
        }

        // 4) fallback interne (src/Generator/.../Stubs)
        $internalStub = realpath(__DIR__."/../Stubs/{$stubName}");
        if ($internalStub && file_exists($internalStub)) {
            return $cache[$stubName] = $internalStub;
        }

        // 5) rechercher dans vendor/*/*/stubs/** (permet d'overrider stubs d'autres packages)
        $vendorMatch = $this->findStubInVendor($stubName);
        if ($vendorMatch) {
            return $cache[$stubName] = $vendorMatch;
=======
        // 2. Stub "packagé" dans /Stubs
        $packageStub = __DIR__."/../../../Stubs/{$stubName}";
        if (file_exists($packageStub)) {
            return $packageStub;
        }

        // 3. Fallback interne dans src/Generator
        $internalStub = __DIR__."/../Stubs/{$stubName}";
        if (file_exists($internalStub)) {
            return $internalStub;
>>>>>>> origin/main
        }

        throw new \RuntimeException("Stub introuvable: {$stubName}");
    }

    /**
     * Cherche un fichier de stub dans vendor/*\/*\/stubs/** en comparant le basename.
     * Retourne le premier chemin trouvé ou null.
     */
    protected function findStubInVendor(string $stubName): ?string
    {
        $vendorDir = base_path('vendor');
        if (! is_dir($vendorDir)) {
            return null;
        }

        $needle = basename($stubName);

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($vendorDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            /* @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            if ($file->getFilename() !== $needle) {
                continue;
            }
            // optionnel : vérifier que "stubs" est dans le chemin
            $path = str_replace('\\', '/', $file->getPathname());
            if (strpos($path, '/stubs/') !== false) {
                // si le nom demandé contient des sous-dossiers, prioriser ceux qui correspondent
                if (substr($path, -strlen($stubName)) === $stubName || str_ends_with($path, '/'.$needle)) {
                    return $path;
                }

                // sinon renvoyer le premier trouvé contenant /stubs/
                return $path;
            }
        }

        return null;
    }
}
