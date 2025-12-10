<?php

use Baracod\Larastarterkit\Generator\Traits\StubResolverTrait;

class StubResolver
{
    use StubResolverTrait;

    public function publicResolveStubPath(string $stubName): string
    {
        return $this->resolveStubPath($stubName);
    }
}

it('resolves published stub', function () {
    // arrange: créer un fichier temporaire dans stubs/larastarterkit
    $dir = base_path('stubs/larastarterkit/testdir');
    @mkdir($dir, 0755, true);
    $file = $dir.'/example.stub';
    file_put_contents($file, 'ok');

    // act
    $resolver = new StubResolver;
    $resolved = $resolver->publicResolveStubPath('testdir/example.stub');

    // assert
    expect(realpath($file))->toBe(realpath($resolved));

    // cleanup
    @unlink($file);
    @rmdir($dir);
});

it('throws when missing', function () {
    $resolver = new StubResolver;
    $resolver->publicResolveStubPath('this/file/should/not/exist.stub');
})->throws(\RuntimeException::class);
