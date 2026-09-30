<?php

// Sets the single release version shared by every packages/* package in the development composer.json.
// Usage: php bin/set-version.php 1.0.0-rc.4   (then: composer update "baracod/*" --no-interaction)

$version = $argv[1] ?? '';
if (! preg_match('/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.\d+)?$/', $version)) {
    fwrite(STDERR, "Usage: php bin/set-version.php <x.y.z[-rc.n]>\n");
    exit(1);
}

$file = dirname(__DIR__).'/composer.json';
// Objects (not arrays) so empty sections such as "extra": {} survive the rewrite.
$composer = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);

$packages = [];
foreach (glob(dirname(__DIR__).'/packages/*/composer.json') as $manifest) {
    $packages[] = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR)['name'];
}

foreach (['require', 'require-dev'] as $section) {
    foreach ($packages as $name) {
        if (isset($composer->{$section}->{$name})) {
            $composer->{$section}->{$name} = $version;
        }
    }
}
foreach ($composer->repositories as $repository) {
    if (($repository->type ?? null) === 'path' && isset($repository->options->versions)) {
        $versions = array_fill_keys($packages, $version);
        ksort($versions);
        $repository->options->versions = (object) $versions;
    }
}

// Keep the file's two-space indentation.
$json = preg_replace_callback('/^( +)/m', fn ($m) => str_repeat(' ', intdiv(strlen($m[1]), 2)), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
file_put_contents($file, $json."\n");
echo "All packages set to {$version}.\n";
