<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\DefinitionFile\Value\CaslPermissions;
use Baracod\Larastarterkit\Generator\DefinitionFile\Value\FrontendConfig;
use PHPUnit\Framework\TestCase;

final class FrontendConfigTest extends TestCase
{
    public function test_default_values_are_all_false(): void
    {
        $config = new FrontendConfig;

        $this->assertFalse($config->hasType);
        $this->assertFalse($config->hasApi);
        $this->assertFalse($config->hasIndex);
        $this->assertFalse($config->hasAddOrEditComponent);
        $this->assertFalse($config->hasReadComponent);
        $this->assertFalse($config->hasMenu);
        $this->assertFalse($config->hasPermission);
        $this->assertSame([], $config->fields);
    }

    public function test_fluent_has_type_sets_and_returns_self(): void
    {
        $config = new FrontendConfig;
        $result = $config->hasType(true);

        $this->assertTrue($config->hasType);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_api_sets_and_returns_self(): void
    {
        $config = new FrontendConfig;
        $result = $config->hasApi(true);

        $this->assertTrue($config->hasApi);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_index_sets_and_returns_self(): void
    {
        $config = new FrontendConfig;
        $result = $config->hasIndex(true);

        $this->assertTrue($config->hasIndex);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_menu_sets_and_returns_self(): void
    {
        $config = new FrontendConfig;
        $result = $config->hasMenu(true);

        $this->assertTrue($config->hasMenu);
        $this->assertSame($config, $result);
    }

    public function test_from_array_to_array_is_idempotent(): void
    {
        $original = [
            'hasType' => true,
            'hasApi' => true,
            'hasLang' => false,
            'hasAddOrEditComponent' => true,
            'hasReadComponent' => false,
            'hasIndex' => true,
            'hasMenu' => false,
            'hasPermission' => true,
            'fields' => [],
            'casl' => ['create' => true, 'read' => true, 'update' => false, 'delete' => false, 'access' => true],
        ];

        $config = FrontendConfig::fromArray($original);
        $exported = $config->toArray();

        $this->assertSame($original['hasType'], $exported['hasType']);
        $this->assertSame($original['hasApi'], $exported['hasApi']);
        $this->assertSame($original['hasLang'], $exported['hasLang']);
        $this->assertSame($original['hasAddOrEditComponent'], $exported['hasAddOrEditComponent']);
        $this->assertSame($original['hasReadComponent'], $exported['hasReadComponent']);
        $this->assertSame($original['hasIndex'], $exported['hasIndex']);
        $this->assertSame($original['hasMenu'], $exported['hasMenu']);
        $this->assertSame($original['hasPermission'], $exported['hasPermission']);
    }

    public function test_casl_via_array_is_idempotent(): void
    {
        $config = new FrontendConfig;
        $config->casl(['create' => true, 'read' => true, 'update' => false, 'delete' => false, 'access' => true]);

        $this->assertTrue($config->casl->create);
        $this->assertTrue($config->casl->read);
        $this->assertFalse($config->casl->update);
        $this->assertFalse($config->casl->delete);
        $this->assertTrue($config->casl->access);
    }

    public function test_casl_via_object_is_idempotent(): void
    {
        $casl = CaslPermissions::fromArray(['create' => false, 'read' => true, 'update' => false, 'delete' => false, 'access' => true]);
        $config = new FrontendConfig;
        $config->casl($casl);

        $this->assertSame($casl, $config->casl);
    }
}
