<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\Backend\Model\ModelPatcher;
use PHPUnit\Framework\TestCase;

final class ModelPatcherTest extends TestCase
{
    public function test_adds_import_to_minimal_class(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nclass Foo\n{\n}\n";

        $result = ModelPatcher::apply($code, ['Illuminate\\Database\\Eloquent\\Model']);

        $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\Model;', $result);
        $this->assertStringContainsString('namespace Baracod\\Larastarterkit\\Core\\Models;', $result);
        $this->assertStringContainsString('class Foo', $result);
    }

    public function test_does_not_duplicate_existing_import(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass Foo\n{\n}\n";

        $result = ModelPatcher::apply($code, ['Illuminate\\Database\\Eloquent\\Model']);

        $occurrences = substr_count($result, 'use Illuminate\\Database\\Eloquent\\Model;');
        $this->assertSame(1, $occurrences);
    }

    public function test_adds_trait_with_import(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nclass Foo\n{\n}\n";

        $result = ModelPatcher::apply($code, [], ['Illuminate\\Database\\Eloquent\\SoftDeletes']);

        $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\SoftDeletes;', $result);
        $this->assertStringContainsString('use SoftDeletes;', $result);
    }

    public function test_does_not_duplicate_existing_trait(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;\n\nclass Foo\n{\n    use SoftDeletes;\n}\n";

        $result = ModelPatcher::apply($code, [], ['Illuminate\\Database\\Eloquent\\SoftDeletes']);

        // Expected: 1 import + 1 "use SoftDeletes;" in class body = 2 occurrences.
        // When trait already exists, both import and trait declaration are skipped.
        $occurrences = substr_count($result, 'SoftDeletes');
        $this->assertSame(2, $occurrences);
    }

    public function test_adds_method_before_closing_brace(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nclass Foo\n{\n}\n";

        $method = "public function bar(): string\n    {\n        return 'ok';\n    }";
        $result = ModelPatcher::apply($code, [], [], [$method]);

        $this->assertStringContainsString('public function bar(): string', $result);
        $this->assertStringContainsString("return 'ok';", $result);
    }

    public function test_skips_method_already_present(): void
    {
        $code = "<?php\n\nnamespace Baracod\\Larastarterkit\\Core\\Models;\n\nclass Foo\n{\n    public function bar(): string\n    {\n        return 'old';\n    }\n}\n";

        $method = "public function bar(): string\n    {\n        return 'new';\n    }";
        $result = ModelPatcher::apply($code, [], [], [$method]);

        $this->assertStringContainsString("return 'old'", $result);
        $this->assertStringNotContainsString("return 'new'", $result);
    }

    public function test_returns_unchanged_for_file_without_class(): void
    {
        $code = "<?php\n\nreturn [];\n";

        $result = ModelPatcher::apply($code, ['Illuminate\\Database\\Eloquent\\Model']);

        $this->assertSame($code, $result);
    }

    public function test_preserves_crlf_end_of_line(): void
    {
        $code = "<?php\r\n\r\nnamespace Baracod\\Larastarterkit\\Core\\Models;\r\n\r\nclass Foo\r\n{\r\n}\r\n";

        $result = ModelPatcher::apply($code, ['Illuminate\\Database\\Eloquent\\Model']);

        $this->assertStringContainsString("\r\n", $result);
    }
}
