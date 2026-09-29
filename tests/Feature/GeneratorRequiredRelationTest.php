<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Backend\Database\FactoryGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;
use RuntimeException;
use Tests\TestCase;

class GeneratorRequiredRelationTest extends TestCase
{
    protected bool $seed = false;

    private function factory(string $related, string $ownerKey = 'id', bool $nullable = false): \Illuminate\Database\Eloquent\Factories\Factory
    {
        static $sequence = 0;
        $name = 'RelationFixture'.++$sequence;
        $model = ModelDefinition::fromArray([
            'key' => 'fixture', 'name' => $name, 'namespace' => 'Modules\\GeneratorRegression\\Models', 'tableName' => 'fixture_records', 'moduleName' => 'GeneratorRegression',
            'fillable' => [['name' => 'owner_id', 'type' => 'integer', 'nullable' => $nullable]],
            'relations' => [['type' => 'belongsTo', 'foreignKey' => 'owner_id', 'ownerKey' => $ownerKey, 'model' => ['fqcn' => $related]]],
        ]);
        $code = (new FactoryGen($model, app(Assistant::class)))->renderFactory([]);
        $file = tempnam(sys_get_temp_dir(), 'relation-factory');
        try {
            file_put_contents($file, $code);
            require $file;
        } finally {
            unlink($file);
        }
        $class = 'Modules\\GeneratorRegression\\Database\\Factories\\'.$name.'Factory';
        $factory = new $class;
        (new \ReflectionProperty($factory, 'model'))->setValue($factory, RelationWithoutFactory::class);

        return $factory;
    }

    public function test_required_relation_without_factory_reports_an_actionable_error(): void
    {
        foreach ([RelationWithoutFactory::class, RelationWithMissingFactory::class] as $related) {
            $factory = $this->factory($related);
            $this->assertSame(['owner_id' => 42], $factory->raw(['owner_id' => 42]), 'An explicit key bypasses automatic resolution.');
            try {
                $factory->raw();
                $this->fail('Missing required relations must not silently return null.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Relation obligatoire owner_id', $e->getMessage());
                $this->assertStringContainsString('fournissez explicitement owner_id', $e->getMessage());
                $this->assertStringContainsString($related, $e->getMessage());
            }
        }
        $this->assertSame(0, User::count());
    }

    public function test_existing_record_without_factory_and_nullable_empty_relation_are_supported(): void
    {
        $this->assertNull($this->factory(RelationWithoutFactory::class, nullable: true)->raw()['owner_id']);
        $user = User::factory()->create();
        $this->assertSame($user->id, $this->factory(RelationWithoutFactory::class)->raw()['owner_id']);
        $this->assertSame($user->email, $this->factory(RelationWithoutFactory::class, 'email')->raw()['owner_id']);
    }

    public function test_factory_is_resolved_at_runtime_and_returns_the_requested_column(): void
    {
        $factory = $this->factory(User::class, 'email');
        $this->assertSame(0, User::count());
        $email = $factory->raw()['owner_id'];
        $this->assertSame(User::sole()->email, $email);
        $this->assertSame($email, $factory->raw()['owner_id']);
        $this->assertSame(1, User::count());
    }
}

class RelationWithoutFactory extends Model
{
    protected $table = 'auth_users';
}

class RelationWithMissingFactory extends RelationWithoutFactory
{
    use HasFactory;
}
