<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\Utils\GeneratorTrait;
use PHPUnit\Framework\TestCase;

final class GeneratorTraitTest extends TestCase
{
    use GeneratorTrait;

    public function test_table_name_to_model_name_prefixed(): void
    {
        // tableNameToModelName strips the first underscore prefix (e.g. "blog_")
        // so "blog_authors" becomes "Author" (singular of "authors")
        $modelName = $this->tableNameToModelName('blog_authors');

        $this->assertSame('Author', $modelName);
    }

    public function test_table_name_to_model_name_no_prefix(): void
    {
        $modelName = $this->tableNameToModelName('users');

        $this->assertSame('User', $modelName);
    }

    public function test_table_name_to_model_name_nested_prefix(): void
    {
        $modelName = $this->tableNameToModelName('blog_article_categories');

        $this->assertSame('ArticleCategory', $modelName);
    }

    public function test_table_name_to_model_name_single_underscore(): void
    {
        $modelName = $this->tableNameToModelName('_orphan');

        $this->assertSame('Orphan', $modelName);
    }
}
