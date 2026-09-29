<?php

namespace Baracod\Larastarterkit\Core\Documents\Services;

use Baracod\Larastarterkit\Core\Documents\Contracts\DocumentContext;

class DocumentRegistry
{
    /** @var array<string, DocumentContext> */
    private array $contexts = [];

    public function register(string $type, DocumentContext $context): void
    {
        if (! preg_match('/\A[a-z][a-z0-9_-]{0,39}\z/', $type) || isset($this->contexts[$type])) {
            throw new \InvalidArgumentException('Invalid or duplicate document context.');
        }
        $this->contexts[$type] = $context;
    }

    public function get(string $type): DocumentContext
    {
        abort_unless(isset($this->contexts[$type]), 404);

        return $this->contexts[$type];
    }

    public function all(): array
    {
        return $this->contexts;
    }
}
