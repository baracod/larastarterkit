<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\DefinitionFile\Value\BackendConfig;
use PHPUnit\Framework\TestCase;

final class BackendConfigTest extends TestCase
{
    public function test_default_values_are_all_false(): void
    {
        $config = new BackendConfig;

        $this->assertFalse($config->hasModel);
        $this->assertFalse($config->hasController);
        $this->assertFalse($config->hasRequest);
        $this->assertFalse($config->hasRoute);
        $this->assertFalse($config->hasPermission);
        $this->assertNull($config->apiRoute);
    }

    public function test_fluent_has_model_sets_and_returns_self(): void
    {
        $config = new BackendConfig;
        $result = $config->hasModel(true);

        $this->assertTrue($config->hasModel);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_controller_sets_and_returns_self(): void
    {
        $config = new BackendConfig;
        $result = $config->hasController(true);

        $this->assertTrue($config->hasController);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_request_sets_and_returns_self(): void
    {
        $config = new BackendConfig;
        $result = $config->hasRequest(true);

        $this->assertTrue($config->hasRequest);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_route_sets_and_returns_self(): void
    {
        $config = new BackendConfig;
        $result = $config->hasRoute(true);

        $this->assertTrue($config->hasRoute);
        $this->assertSame($config, $result);
    }

    public function test_fluent_has_permission_sets_and_returns_self(): void
    {
        $config = new BackendConfig;
        $result = $config->hasPermission(true);

        $this->assertTrue($config->hasPermission);
        $this->assertSame($config, $result);
    }

    public function test_from_array_to_array_is_idempotent(): void
    {
        $original = [
            'hasModel' => true,
            'hasController' => false,
            'hasRequest' => true,
            'hasRoute' => false,
            'hasPermission' => true,
            'apiRoute' => 'api/blog/authors',
        ];

        $config = BackendConfig::fromArray($original);
        $exported = $config->toArray();

        $this->assertSame($original['hasModel'], $exported['hasModel']);
        $this->assertSame($original['hasController'], $exported['hasController']);
        $this->assertSame($original['hasRequest'], $exported['hasRequest']);
        $this->assertSame($original['hasRoute'], $exported['hasRoute']);
        $this->assertSame($original['hasPermission'], $exported['hasPermission']);
        $this->assertSame($original['apiRoute'], $exported['apiRoute']);
    }

    public function test_api_route_can_be_set_and_cleared(): void
    {
        $config = new BackendConfig;

        $config->withApiRoute('api/blog/authors');
        $this->assertSame('api/blog/authors', $config->apiRoute);

        $config->clearApiRoute();
        $this->assertNull($config->apiRoute);
    }
}
