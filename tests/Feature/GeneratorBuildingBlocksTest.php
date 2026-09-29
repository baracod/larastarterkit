<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Generator\Ai\Fallback\FieldSyntax;
use Baracod\Larastarterkit\Generator\Ai\Fallback\ValidationHints;
use Baracod\Larastarterkit\Generator\Backend\Database\MigrationGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RequestGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RouteGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Baracod\Larastarterkit\Generator\Support\TableImporter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class GeneratorBuildingBlocksTest extends TestCase
{
    protected bool $seed = false;

    public function test_field_syntax_parses_types_modifiers_enums_and_foreign_keys(): void
    {
        $parsed = FieldSyntax::parse('Name:string:120:unique, status : enum(draft|published) : default=draft, price:decimal:nullable, active:bool, category_id:fk(shop_categories), label:string:label=Libellé');
        $fields = collect($parsed['fields'])->keyBy('name');

        $this->assertSame(['name', 'status', 'price', 'active', 'category_id', 'label'], $fields->keys()->all());
        $this->assertSame(120, $fields['name']->length);
        $this->assertTrue($fields['name']->unique);
        $this->assertFalse($fields['name']->isNullable());
        $this->assertSame(['draft', 'published'], $fields['status']->enumValues);
        $this->assertSame('draft', $fields['status']->defaultValue);
        $this->assertSame(FieldType::Float, $fields['price']->type);
        $this->assertTrue($fields['price']->isNullable());
        $this->assertSame(FieldType::Boolean, $fields['active']->type);
        $this->assertSame('Libellé', $fields['label']->label);
        $this->assertSame([['foreignKey' => 'category_id', 'table' => 'shop_categories', 'ownerKey' => 'id']], $parsed['relations']);

        foreach (['name:money', 'name:string:shiny', ''] as $invalid) {
            try {
                FieldSyntax::parse($invalid);
                $this->fail("« {$invalid} » should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_legacy_definitions_keep_their_meaning_and_extra_options_survive(): void
    {
        $legacy = FieldDefinition::fromArray(['name' => 'title', 'type' => 'string', 'defaultValue' => null]);
        $this->assertTrue($legacy->isNullable(), 'Old files: a null default meant optional.');
        $this->assertSame(['name' => 'title', 'type' => 'string', 'defaultValue' => null, 'customizedType' => ''], $legacy->toArray(), 'Unset attributes are not written.');
        $this->assertFalse(FieldDefinition::fromArray(['name' => 'title', 'type' => 'string', 'nullable' => false])->isNullable());
        foreach (['uuid', 'hash'] as $name) {
            $this->assertFalse(FieldDefinition::fromArray(['name' => $name, 'type' => 'string', 'nullable' => false])->isNullable());
            $this->assertTrue(FieldDefinition::fromArray(['name' => $name, 'type' => 'string', 'nullable' => true])->isNullable());
            $this->assertTrue(FieldDefinition::fromArray(['name' => $name, 'type' => 'string'])->isNullable());
        }

        $model = ModelDefinition::fromArray([
            'key' => 'post', 'name' => 'Post', 'namespace' => 'Modules\\Blog\\Models', 'tableName' => 'blog_posts', 'moduleName' => 'Blog',
            'fillable' => [], 'traits' => ['Baracod\\Larastarterkit\\Core\\Traits\\Auditable'], 'props' => ['hidden' => ['secret']], 'softDeletes' => true,
        ]);
        $array = $model->toArray();
        $this->assertSame(['Baracod\\Larastarterkit\\Core\\Traits\\Auditable'], $array['traits']);
        $this->assertSame(['hidden' => ['secret']], $array['props']);
        $this->assertTrue($model->usesSoftDeletes());
        $this->assertTrue($model->usesTimestamps());
    }

    public function test_migration_is_rendered_from_the_definition(): void
    {
        $model = ModelDefinition::fromArray([
            'key' => 'product', 'name' => 'Product', 'namespace' => 'Modules\\Shop\\Models', 'tableName' => 'shop_products', 'moduleName' => 'Shop',
            'softDeletes' => true,
            'fillable' => [
                ['name' => 'name', 'type' => 'string', 'nullable' => false, 'length' => 120, 'unique' => true],
                ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'enumValues' => ['draft', "it's"], 'defaultValue' => 'draft'],
                ['name' => 'price', 'type' => 'float', 'nullable' => true],
                ['name' => 'active', 'type' => 'boolean', 'nullable' => false],
                ['name' => 'category_id', 'type' => 'integer', 'nullable' => false],
                ['name' => 'owner_id', 'type' => 'integer', 'nullable' => true],
            ],
            'relations' => [
                ['type' => 'belongsTo', 'foreignKey' => 'category_id', 'table' => 'shop_categories'],
                ['type' => 'belongsTo', 'foreignKey' => 'owner_id', 'table' => 'auth_users'],
            ],
        ]);

        $code = (new MigrationGen($model))->render();

        $this->assertStringContainsString("Schema::create('shop_products'", $code);
        $this->assertStringContainsString("\$table->string('name', 120)->unique();", $code);
        $this->assertStringContainsString("\$table->enum('status', ['draft', 'it\\'s'])->default('draft');", $code);
        $this->assertStringContainsString("\$table->decimal('price', 15, 2)->nullable();", $code);
        $this->assertStringContainsString("\$table->boolean('active')->default(false);", $code);
        $this->assertStringContainsString("\$table->foreignId('category_id')->constrained('shop_categories')->restrictOnDelete();", $code);
        $this->assertStringContainsString("\$table->foreignId('owner_id')->nullable()->constrained('auth_users')->nullOnDelete();", $code);
        $this->assertStringContainsString('$table->softDeletes();', $code);
        $file = tempnam(sys_get_temp_dir(), 'migration');
        File::put($file, $code);
        $this->assertSame('0', exec('php -l '.escapeshellarg($file).' > /dev/null 2>&1; echo $?'), 'The migration is valid PHP.');
        File::delete($file);
    }

    public function test_generated_migration_enforces_unique_foreign_keys_and_required_identifiers(): void
    {
        config(['database.connections.generator_regression' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('generator_regression');
        $file = tempnam(sys_get_temp_dir(), 'generator-migration');

        try {
            Schema::create('generator_parents', function ($table) {
                $table->id();
            });
            DB::table('generator_parents')->insert(['id' => 1]);
            $model = ModelDefinition::fromArray([
                'key' => 'profile', 'name' => 'Profile', 'namespace' => 'Modules\\Shop\\Models', 'tableName' => 'generator_profiles', 'moduleName' => 'Shop',
                'fillable' => [
                    ['name' => 'parent_id', 'type' => 'integer', 'nullable' => false, 'unique' => true, 'defaultValue' => 1],
                    ['name' => 'uuid', 'type' => 'uuid', 'nullable' => false],
                    ['name' => 'hash', 'type' => 'string', 'nullable' => false],
                ],
                'relations' => [['type' => 'belongsTo', 'foreignKey' => 'parent_id', 'table' => 'generator_parents']],
            ]);
            File::put($file, (new MigrationGen($model))->render());
            (require $file)->up();
            DB::table('generator_profiles')->insert(['uuid' => 'first', 'hash' => 'first']);
            $this->assertSame(1, DB::table('generator_profiles')->value('parent_id'));
            foreach ([['uuid' => 'second', 'hash' => 'second'], ['uuid' => null, 'hash' => 'second'], ['uuid' => 'second', 'hash' => null]] as $record) {
                DB::table('generator_profiles')->delete();
                if ($record['uuid'] !== null && $record['hash'] !== null) {
                    DB::table('generator_profiles')->insert(['uuid' => 'first', 'hash' => 'first']);
                }
                try {
                    DB::table('generator_profiles')->insert($record);
                    $this->fail('The generated database constraint must reject this record.');
                } catch (QueryException $e) {
                    $this->assertStringContainsString('constraint failed', $e->getMessage());
                }
            }
        } finally {
            File::delete($file);
            DB::setDefaultConnection($original);
            DB::purge('generator_regression');
        }
    }

    public function test_import_preserves_enum_values_and_default_case(): void
    {
        $schema = Schema::getFacadeRoot();
        try {
            Schema::shouldReceive('hasTable')->with('enum_fixture')->andReturn(true);
            Schema::shouldReceive('getIndexes')->with('enum_fixture')->andReturn([]);
            Schema::shouldReceive('getForeignKeys')->with('enum_fixture')->andReturn([]);
            Schema::shouldReceive('getColumns')->with('enum_fixture')->andReturn([
                ['name' => 'status', 'type' => "enum('Draft','Published')", 'type_name' => 'enum', 'nullable' => false, 'default' => "'Draft'"],
            ]);
            $field = TableImporter::import('enum_fixture')['fields'][0];
            $this->assertSame(['Draft', 'Published'], $field->enumValues);
            $this->assertSame('Draft', $field->effectiveDefault());
            $this->assertContains($field->effectiveDefault(), $field->enumValues);
        } finally {
            Schema::swap($schema);
        }
    }

    public function test_request_rules_escape_hand_edited_definitions(): void
    {
        $directory = base_path('Modules/GeneratorRulesFixture');
        $this->assertDirectoryDoesNotExist($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/module.json', json_encode([
            'name' => 'GeneratorRulesFixture', 'models' => ['item' => [
                'key' => 'item', 'name' => 'Item', 'namespace' => 'Modules\\GeneratorRulesFixture\\Models', 'tableName' => 'fixture_items', 'moduleName' => 'GeneratorRulesFixture',
                'fillable' => [
                    ['name' => 'code', 'type' => 'string', 'nullable' => false, 'unique' => true, 'length' => 20],
                    ['name' => 'kind', 'type' => 'enum', 'nullable' => true, 'enumValues' => ["a'); system('id"]],
                    ['name' => 'legacy', 'type' => 'string', 'customizedType' => "string|max:10'] ; exit; ['"],
                ],
                'relations' => [['type' => 'belongsTo', 'foreignKey' => 'code', 'table' => 'fixture_codes', 'ownerKey' => 'code']],
            ]],
        ]));

        try {
            $rules = (new RequestGen('item', 'GeneratorRulesFixture'))->buildRules();
            $this->assertSame(["'required'", "'string'", "Rule::exists('fixture_codes', 'code')", "Rule::unique('fixture_items', 'code')->ignore(\$this->recordId())"], $rules['code']);
            $this->assertSame(["'nullable'", "'string'", "Rule::in(['a\\'); system(\\'id'])"], $rules['kind']);
            $this->assertSame("'max:10\\'] ; exit; [\\''", $rules['legacy'][2]);

            (new RequestGen('item', 'GeneratorRulesFixture'))->generate();
            $file = $directory.'/app/Http/Requests/ItemRequest.php';
            $this->assertSame('0', exec('php -l '.escapeshellarg($file).' > /dev/null 2>&1; echo $?'), 'Generated request is valid PHP.');
            $this->assertStringNotContainsString('messages()', File::get($file));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_routes_follow_the_group_prefix_of_multi_word_modules(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'routes');
        File::put($file, "<?php\nRoute::middleware(['auth:sanctum'])->prefix('v1/stockmanagement')->group(function () {\n    //{{ next-route }}\n});\n");

        $result = (new RouteGen($file))->addApiResource('products', 'ProductController', 'StockManagement', 'stock_products');

        $this->assertSame(['statut' => 'added', 'apiRoute' => 'api/stockmanagement/products'], $result);
        $code = File::get($file);
        $this->assertStringContainsString("Route::post('products/bulk-delete', [\\Modules\\StockManagement\\Http\\Controllers\\ProductController::class, 'destroyMany'])", $code);
        $this->assertStringContainsString("'ability:delete,stock_products'", $code);
        $this->assertSame('already_exists', (new RouteGen($file))->addApiResource('products', 'ProductController', 'StockManagement')['statut']);

        // Un ancien fichier sans route groupée la reçoit, juste avant la ressource.
        File::put($file, "<?php\nRoute::prefix('api/v1/legacy')->group(function () {\n    Route::apiResource('items', X::class);\n    //{{ next-route }}\n});\n");
        $this->assertSame('api/legacy/items', (new RouteGen($file))->addApiResource('items', 'ItemController', 'Legacy')['apiRoute']);
        $this->assertMatchesRegularExpression("/bulk-delete.*\\n\\s+Route::apiResource\\('items'/", File::get($file));
        File::delete($file);
    }

    public function test_routes_without_module_target_application_controllers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'routes');
        File::put($file, "<?php\n//{{ next-route }}\n");

        (new RouteGen($file))->addApiResource('posts', 'PostController');

        $this->assertStringContainsString('\\App\\Http\\Controllers\\PostController::class', File::get($file));
        File::delete($file);
    }

    public function test_existing_tables_are_imported_with_real_constraints(): void
    {
        $imported = TableImporter::import('documentation_collections');
        $fields = collect($imported['fields'])->keyBy('name');

        $this->assertFalse($fields['title']->isNullable());
        $this->assertTrue($fields['description']->isNullable());
        $this->assertTrue($fields['slug']->unique);
        // SQLite ne conserve pas la longueur des VARCHAR ; MariaDB/MySQL oui.
        $this->assertSame(DB::getDriverName() === 'sqlite' ? null : 80, $fields['slug']->length);
        $this->assertSame(FieldType::Text, $fields['description']->type);
        $this->assertTrue($imported['timestamps']);
        $this->assertFalse($imported['softDeletes']);
        $this->assertContains(['foreignKey' => 'collection_id', 'table' => 'documentation_collections', 'ownerKey' => 'id'], TableImporter::import('documentation_editions')['relations']);
    }

    public function test_validation_hints_follow_field_names(): void
    {
        $this->assertSame(['email', 'max:255'], ValidationHints::for(new FieldDefinition('billing_email', FieldType::String)));
        $this->assertSame(['url', 'max:255'], ValidationHints::for(new FieldDefinition('website', FieldType::String)));
        $this->assertSame(['min:0'], ValidationHints::for(new FieldDefinition('price', FieldType::Float)));
        $this->assertSame([], ValidationHints::for(new FieldDefinition('notes', FieldType::Text)));
        // Les formats déduits du nom ne s'appliquent qu'aux chaînes (régression : booléen "email_verified" → règle email).
        $this->assertSame([], ValidationHints::for(new FieldDefinition('email_verified', FieldType::Boolean)));
        $this->assertSame([], ValidationHints::for(new FieldDefinition('phone_confirmed', FieldType::Boolean)));
        $this->assertSame([], ValidationHints::for(new FieldDefinition('email_body', FieldType::Text)));
    }

    public function test_foreign_keys_to_other_columns_keep_their_referenced_column(): void
    {
        $parsed = FieldSyntax::parse('country_code:fk(countries.code), category_id:fk(shop_categories)');
        $this->assertSame(FieldType::String, $parsed['fields'][0]->type);
        $this->assertSame([
            ['foreignKey' => 'country_code', 'table' => 'countries', 'ownerKey' => 'code'],
            ['foreignKey' => 'category_id', 'table' => 'shop_categories', 'ownerKey' => 'id'],
        ], $parsed['relations']);

        $model = ModelDefinition::fromArray([
            'key' => 'store', 'name' => 'Store', 'namespace' => 'Modules\\Shop\\Models', 'tableName' => 'shop_stores', 'moduleName' => 'Shop',
            'fillable' => [['name' => 'country_code', 'type' => 'string', 'nullable' => false, 'length' => 2]],
            'relations' => [['type' => 'belongsTo', 'foreignKey' => 'country_code', 'table' => 'countries', 'ownerKey' => 'code']],
        ]);
        $code = (new MigrationGen($model))->render();
        $this->assertStringContainsString("\$table->string('country_code', 2);", $code);
        $this->assertStringContainsString("\$table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();", $code);
        $this->assertStringNotContainsString('foreignId', $code);
    }

    public function test_factories_create_required_related_records_when_none_exist(): void
    {
        $user = ['name' => 'User', 'namespace' => 'Modules\\Auth\\Models', 'fqcn' => 'Modules\\Auth\\Models\\User', 'path' => ''];
        $model = ModelDefinition::fromArray([
            'key' => 'note', 'name' => 'Note', 'namespace' => 'Modules\\Shop\\Models', 'tableName' => 'shop_notes', 'moduleName' => 'Shop',
            'fillable' => [['name' => 'owner_id', 'type' => 'integer', 'nullable' => false], ['name' => 'reviewer_id', 'type' => 'integer', 'nullable' => true]],
            'relations' => [
                ['type' => 'belongsTo', 'foreignKey' => 'owner_id', 'table' => 'auth_users', 'ownerKey' => 'id', 'moduleName' => 'Auth', 'model' => $user],
                ['type' => 'belongsTo', 'foreignKey' => 'reviewer_id', 'table' => 'auth_users', 'ownerKey' => 'id', 'moduleName' => 'Auth', 'model' => $user],
            ],
        ]);

        $code = (new \Baracod\Larastarterkit\Generator\Backend\Database\FactoryGen($model, app(\Baracod\Larastarterkit\Generator\Ai\Assistant::class)))->renderFactory([]);

        $this->assertStringContainsString("'owner_id' => fn () => \$this->requiredRelation(\\Modules\\Auth\\Models\\User::class, 'id', 'owner_id'),", $code);
        $this->assertStringContainsString("'reviewer_id' => fn () => \\Modules\\Auth\\Models\\User::query()->inRandomOrder()->value('id'),", $code);
    }
}
