<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Model;

use Illuminate\Support\Str;

final class ModelPatcher
{
    /**
     * @param  string  $code  Contenu complet du fichier modèle Laravel
     * @param  string[]  $imports  FQCN à importer (ex: Illuminate\Database\Eloquent\Relations\HasMany)
     * @param  string[]  $traits  Noms de traits OU FQCN de traits (ex: SoftDeletes ou Illuminate\Database\Eloquent\SoftDeletes)
     * @param  string[]  $methods  Blocs de méthodes (code PHP complet de la méthode)
     * @return string Code patché
     */
    public static function apply(string $code, array $imports = [], array $traits = [], array $methods = []): string
    {
        // Conserve les fins de ligne d’origine
        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $src = str_replace(["\r\n", "\r"], "\n", $code); // normalise en \n pour le travail

        // Trouve namespace et début de la classe
        $nsPos = self::matchPos('/^namespace\s+[^;]+;/m', $src);
        $classOpenPos = self::matchPos('/^\s*(?:abstract\s+|final\s+)?class\s+\w+[^{]*\{/m', $src);
        if ($classOpenPos === -1) {
            // Fichier atypique
            return $code;
        }

        // Section top-level (entre namespace et ouverture de class) pour les imports
        $nsEnd = ($nsPos !== -1) ? self::matchEnd('/^namespace\s+[^;]+;/m', $src) : 0;
        $importsBlockStart = ($nsEnd !== -1) ? $nsEnd : 0;
        $importsBlockLen = max(0, $classOpenPos - $importsBlockStart);
        $topSegment = substr($src, $importsBlockStart, $importsBlockLen);

        // 1) IMPORTS
        // Normalise: si un trait est un FQCN, on l’importe aussi et n’utilise que son short name dans la classe
        [$imports, $traitShortNames] = self::normalizeTraitsAndCollectImports($traits, $imports);

        $existingImports = self::collectTopImports($topSegment);
        $normalize = function (string $s): string {
            $s = trim($s);
            if (Str::startsWith($s, 'use ')) {
                $s = substr($s, 4);
            }
            $s = rtrim($s, " \t\n\r\0\x0B;");
            $s = ltrim($s, '\\');
            $s = preg_replace('/\s+/', '', $s);

            return $s;
        };
        $imports = array_map($normalize, $imports);
        $existingImports = array_map($normalize, $existingImports);

        $toAddImports = array_values(array_diff($imports, $existingImports));
        if (! empty($toAddImports)) {
            $newImportsText = '';
            foreach ($toAddImports as $fqcn) {
                // Nettoyer les espaces
                $fqcn = trim($fqcn);

                if (! Str::startsWith($fqcn, 'use ')) {
                    $fqcn = 'use '.$fqcn;
                }

                if (! Str::endsWith($fqcn, ';')) {
                    $fqcn .= ';';
                }

                $newImportsText .= $fqcn.PHP_EOL;
            }

            // Point d’insertion : après le dernier import existant, sinon après le namespace, sinon tout début
            $lastImportEnd = self::matchLastEnd('/^use\s+[^;]+;/m', $topSegment);
            $insertPos = $importsBlockStart + ($lastImportEnd !== -1 ? $lastImportEnd : 0);

            // S’il n’y avait ni namespace ni imports, ajoute une ligne vide au besoin
            $prefix = ($lastImportEnd === -1 && $nsEnd !== -1) ? "\n" : '';
            $src = substr($src, 0, $insertPos).$prefix."\n".$newImportsText."\n".substr($src, $insertPos);

            // Ajuste classOpenPos (le code a grandi)
            $classOpenPos += strlen($prefix.$newImportsText);
        }

        // 2) TRAITS (dans le corps de classe)
        // Localise la zone corps de classe: entre '{' d’ouverture et '}' final
        $classBodyStart = strpos($src, '{', $classOpenPos);
        $classBodyEnd = self::findClassClosingBrace($src, $classBodyStart);
        if ($classBodyStart !== false && $classBodyEnd !== -1) {
            $classBody = substr($src, $classBodyStart + 1, $classBodyEnd - ($classBodyStart + 1));

            // Recherche d’un "use ...;" de traits au niveau classe (pas les closures, qui sont "use (...)" sans ;)
            if (preg_match('/^\s*use\s+([^;]+);/m', $classBody, $m, PREG_OFFSET_CAPTURE)) {
                $useBlock = $m[0][0];
                $useBlockStart = (int) $m[0][1];
                $list = $m[1][0]; // contenu entre 'use ' et ';'

                $currentTraits = array_map(static fn ($s) => trim($s), explode(',', $list));
                // Nettoie noms (garde le short name)
                $currentTraits = array_map([self::class, 'shortName'], $currentTraits);

                $finalTraits = $currentTraits;
                foreach ($traitShortNames as $t) {
                    if (! in_array($t, $finalTraits, true)) {
                        $finalTraits[] = $t;
                    }
                }
                // Rien à faire si rien de nouveau
                if ($finalTraits !== $currentTraits) {
                    $newUseBlock = 'use '.implode(', ', $finalTraits).';';
                    $classBody = substr_replace($classBody, $newUseBlock, $useBlockStart, strlen($useBlock));
                }
            } else {
                // Aucun use de trait existant -> en insérer un après l’accolade d’ouverture
                if (! empty($traitShortNames)) {
                    $newUse = '    use '.implode(', ', $traitShortNames).';'."\n\n";
                    $classBody = $newUse.$classBody;
                }
            }

            // Réécrit le corps dans $src
            $src = substr($src, 0, $classBodyStart + 1)
                .$classBody
                .substr($src, $classBodyEnd);
        }

        // 3) MÉTHODES (insertion avant la dernière '}' de la classe)
        if ($classBodyStart !== false && $classBodyEnd !== -1 && ! empty($methods)) {
            // Recalcule le corps après éventuelles modifs
            $classBody = substr($src, $classBodyStart + 1, $classBodyEnd - ($classBodyStart + 1));

            // Collecte des noms de méthodes existantes
            $existingMethodNames = self::collectMethodNames($classBody);

            $toAppend = '';
            foreach ($methods as $methodCode) {
                $name = self::extractMethodName($methodCode);
                if ($name === null || in_array($name, $existingMethodNames, true)) {
                    continue; // skip doublons ou code sans function
                }
                // Indentation douce (4 espaces min)
                $normalized = self::indentBlock($methodCode, 4);
                // Toujours deux sauts de ligne avant une nouvelle méthode
                $toAppend .= "\n\n".$normalized."\n";
            }

            if ($toAppend !== '') {
                $src = substr($src, 0, $classBodyEnd)
                    .rtrim($toAppend, "\n")
                    ."\n"
                    .substr($src, $classBodyEnd);
            }
        }

        // Restaure l’EOL d’origine
        $src = str_replace("\n", $eol, $src);

        return $src;
    }

    /* ====================== Helpers ====================== */

    private static function matchPos(string $pattern, string $text): int
    {
        return preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : -1;
    }

    private static function matchEnd(string $pattern, string $text): int
    {
        return preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] + strlen($m[0][0]) : -1;
    }

    private static function matchLastEnd(string $pattern, string $text): int
    {
        $found = -1;
        if (preg_match_all($pattern, $text, $all, PREG_OFFSET_CAPTURE)) {
            $last = end($all[0]);
            $found = $last[1] + strlen($last[0]);
        }

        return $found;
    }

    /**
     * Essaie de trouver l’accolade fermante correspondant à l’ouverture de la classe.
     * (Parcours naïf comptant les accolades – suffisant pour fichiers modèles standards.)
     */
    private static function findClassClosingBrace(string $text, int|false $openPos): int
    {
        if ($openPos === false) {
            return -1;
        }
        $depth = 0;
        $len = strlen($text);
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return -1;
    }

    private static function shortName(string $fqcnOrName): string
    {
        $fqcnOrName = trim($fqcnOrName, ' \\');
        $pos = strrpos($fqcnOrName, '\\');

        return $pos === false ? $fqcnOrName : substr($fqcnOrName, $pos + 1);
    }

    private static function normalizeTraitsAndCollectImports(array $traits, array $imports): array
    {
        $traitShortNames = [];
        foreach ($traits as $t) {
            $t = trim($t, ' \\');
            if ($t === '') {
                continue;
            }
            $short = self::shortName($t);
            $traitShortNames[] = $short;
            // Si c’est un FQCN, on l’ajoute aux imports
            if (str_contains($t, '\\')) {
                $imports[] = $t;
            }
        }
        // Unifie
        $imports = array_values(array_unique($imports));
        $traitShortNames = array_values(array_unique($traitShortNames));

        return [$imports, $traitShortNames];
    }

    /**
     * Récupère les imports top-level (entre namespace et class)
     *
     * @return string[] liste de FQCN
     */
    private static function collectTopImports(string $segment): array
    {
        $out = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $segment, $m)) {
            foreach ($m[1] as $fqcn) {
                $out[] = trim($fqcn);
            }
        }

        return $out;
    }

    /**
     * Retourne les noms des méthodes trouvées dans un corps de classe.
     *
     * @return string[]
     */
    private static function collectMethodNames(string $classBody): array
    {
        $names = [];
        if (preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $classBody, $m)) {
            $names = $m[1];
        }

        return $names;
    }

    private static function extractMethodName(string $methodCode): ?string
    {
        return preg_match('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $methodCode, $m)
            ? $m[1]
            : null;
    }

    private static function indentBlock(string $code, int $spaces): string
    {
        $indent = str_repeat(' ', $spaces);
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        $lines = explode("\n", trim($code, "\n"));
        foreach ($lines as &$l) {
            // N’indente pas les lignes vides
            $l = ($l === '') ? '' : $indent.$l;
        }

        return implode("\n", $lines);
    }
}
