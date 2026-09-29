<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Generator\Ai\Agents\EntityDesignAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\FakeDataAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\TranslationAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\ValidationAgent;
use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Ai\Fallback\FakeData;
use Baracod\Larastarterkit\Generator\Ai\GeneratorAi;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class GeneratorAiTest extends TestCase
{
    protected bool $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['generator.ai.provider' => null, 'generator.ai.model' => null]);
        foreach (array_keys(config('ai.providers', [])) as $provider) {
            config(["ai.providers.{$provider}.key" => null]);
        }
    }

    private function enableAi(): void
    {
        config([
            'generator.ai.enabled' => true,
            'generator.ai.provider' => null,
            'ai.default' => 'openai',
            'ai.providers.openai.key' => 'test-key',
        ]);
        app(GeneratorAi::class)->enable();
    }

    private function model(): ModelDefinition
    {
        $model = ModelDefinition::new('supplier', 'Supplier', 'Modules\\Shop\\Models', 'shop_suppliers', 'Shop');
        $model->addField((new FieldDefinition('name', FieldType::String, nullable: false))->length(120));
        $model->addField(new FieldDefinition('contact_email', FieldType::String, nullable: true));
        $model->addField(new FieldDefinition('delay_days', FieldType::Integer, nullable: true));

        return $model;
    }

    public function test_ai_is_optional_and_every_task_falls_back_to_deterministic_rules(): void
    {
        config(['generator.ai.enabled' => true, 'ai.providers.openai.key' => null, 'ai.providers.anthropic.key' => null, 'ai.providers.gemini.key' => null]);
        TranslationAgent::fake()->preventStrayPrompts();
        $ai = app(GeneratorAi::class);
        $assistant = app(Assistant::class);

        $this->assertFalse($ai->enabled());
        $this->assertStringContainsString('aucun fournisseur', (string) $ai->unavailableReason());

        $labels = $assistant->translations('Supplier', 'Shop', ['name', 'contact_email', 'delay_days']);
        $this->assertFalse($labels->fromAi);
        $this->assertSame('Nom', $labels->value['fr']['field']['name']);
        $this->assertSame('Contact email', $labels->value['fr']['field']['contactEmail']);
        $this->assertSame('Suppliers', $labels->value['en']['titlePlural']);

        $rules = $assistant->validationRules($this->model());
        $this->assertSame(['max:120'], $rules->value['name']);
        $this->assertSame(['email', 'max:255'], $rules->value['contact_email']);
        $this->assertSame('safeEmail', $assistant->fakeFormatters($this->model())->value['contact_email']);
        TranslationAgent::assertNeverPrompted();
    }

    public function test_provider_is_detected_from_configured_keys_and_can_be_forced_or_disabled(): void
    {
        config(['generator.ai.enabled' => true, 'ai.default' => 'openai', 'ai.providers.openai.key' => null, 'ai.providers.anthropic.key' => null, 'ai.providers.gemini.key' => 'legacy-key']);
        $ai = app(GeneratorAi::class);
        $this->assertSame('gemini', $ai->provider(), 'An existing GEMINI_API_KEY keeps working.');

        config(['generator.ai.provider' => 'ollama']);
        $this->assertSame('ollama', $ai->provider(), 'Local servers are used when chosen explicitly.');
        config(['generator.ai.provider' => 'mistral', 'ai.providers.mistral.key' => null]);
        $this->assertNull($ai->provider());

        config(['generator.ai.provider' => null, 'generator.ai.features.validation' => false]);
        $this->assertTrue($ai->enabled('translations'));
        $this->assertFalse($ai->enabled('validation'));
        $ai->disable();
        $this->assertFalse($ai->enabled('translations'));
        $this->assertStringContainsString('--no-ai', (string) $ai->unavailableReason());
    }

    public function test_translations_from_ai_are_cleaned_and_completed(): void
    {
        $this->enableAi();
        TranslationAgent::fake([[
            'fr' => ['title' => 'Fournisseur', 'titlePlural' => '<b>Fournisseurs</b>', 'menuTitle' => 'Fournisseurs', 'menuDescription' => 'Gérer les fournisseurs', 'field' => ['name' => 'Raison sociale', 'contactEmail' => '  ']],
            'en' => 'not an object',
        ]]);

        $outcome = app(Assistant::class)->translations('Supplier', 'Shop', ['name', 'contact_email']);

        $this->assertTrue($outcome->fromAi);
        $this->assertSame('Fournisseurs', $outcome->value['fr']['titlePlural'], 'Markup is stripped.');
        $this->assertSame('Raison sociale', $outcome->value['fr']['field']['name']);
        $this->assertSame('Contact email', $outcome->value['fr']['field']['contactEmail'], 'Empty labels fall back to rules.');
        $this->assertSame('Supplier', $outcome->value['en']['title'], 'Invalid locales fall back to rules.');
        TranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'contactEmail (colonne contact_email)'));
    }

    public function test_ai_failures_never_break_generation(): void
    {
        $this->enableAi();
        TranslationAgent::fake(fn () => throw new RuntimeException('quota exceeded'));
        $ai = app(GeneratorAi::class);

        $outcome = app(Assistant::class)->translations('Supplier', 'Shop', ['name']);

        $this->assertFalse($outcome->fromAi);
        $this->assertStringContainsString('quota exceeded', (string) $outcome->reason);
        $this->assertSame('Nom', $outcome->value['fr']['field']['name']);
        $this->assertFalse(collect($ai->journal())->last()['fromAi']);
    }

    public function test_entity_design_from_a_description_is_sanitized(): void
    {
        $this->enableAi();
        EntityDesignAgent::fake([[
            'modelName' => 'supplier', 'tableName' => 'shop_suppliers',
            'fields' => [
                ['name' => 'Company Name', 'type' => 'string', 'nullable' => false, 'unique' => true, 'length' => 150, 'enumValues' => [], 'label' => 'Raison <i>sociale</i>'],
                ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'unique' => false, 'length' => null, 'enumValues' => ['active', "drop table'--", 'blocked'], 'label' => 'Statut'],
                ['name' => 'kind', 'type' => 'enum', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Type'],
                ['name' => 'country_id', 'type' => 'integer', 'nullable' => false, 'unique' => false, 'length' => 10, 'enumValues' => [], 'label' => 'Pays'],
                ['name' => 'owner_id', 'type' => 'integer', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Responsable'],
                ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Créé'],
                ['name' => 'rating', 'type' => 'stars', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Note'],
                ['name' => 'company_name', 'type' => 'text', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Doublon'],
            ],
            'relations' => [
                ['foreignKey' => 'country_id', 'table' => 'shop_countries'],
                ['foreignKey' => 'owner_id', 'table' => 'auth_users'],
                ['foreignKey' => 'name', 'table' => 'auth_users'],
            ],
        ]]);

        $design = app(Assistant::class)->designEntity('Shop', 'Fournisseurs avec raison sociale unique, statut et pays.', null, ['auth_users'])->value;

        $this->assertSame('Supplier', $design['modelName']);
        $fields = collect($design['fields'])->keyBy('name');
        $this->assertSame(['company_name', 'status', 'kind', 'country_id', 'owner_id'], $fields->keys()->all());
        $this->assertTrue($fields['company_name']->unique);
        $this->assertSame(150, $fields['company_name']->length);
        $this->assertSame('Raison sociale', $fields['company_name']->label);
        $this->assertSame(['active', 'blocked'], $fields['status']->enumValues);
        $this->assertSame(FieldType::String, $fields['kind']->type, 'An enum without values becomes a string.');
        $this->assertNull($fields['country_id']->length);
        $this->assertSame([['foreignKey' => 'owner_id', 'table' => 'auth_users']], $design['relations'], 'Only relations to real tables are kept.');
    }

    public function test_field_syntax_skips_the_ai_even_when_it_is_available(): void
    {
        $this->enableAi();
        EntityDesignAgent::fake()->preventStrayPrompts();

        $design = app(Assistant::class)->designEntity('Shop', 'name:string:120, status:enum(a|b):default=a, category_id:fk(shop_categories), bio:text:nullable', 'Product');

        $this->assertSame('shop_products', $design->value['tableName']);
        $this->assertSame(['name', 'status', 'category_id', 'bio'], array_map(fn ($f) => $f->name, $design->value['fields']));
        $this->assertSame([['foreignKey' => 'category_id', 'table' => 'shop_categories', 'ownerKey' => 'id']], $design->value['relations']);
        EntityDesignAgent::assertNeverPrompted();
    }

    public function test_validation_and_fake_data_suggestions_are_restricted_to_safe_whitelists(): void
    {
        $this->enableAi();
        ValidationAgent::fake([[
            'fields' => [
                ['field' => 'name', 'rules' => ['max:100', 'required', 'exists:auth_users,id', 'regex:/^[A-Z]/', 'regex:/a|b/', 'min:2']],
                ['field' => 'delay_days', 'rules' => ['min:0', 'max:365', 'evil:"; system("id")']],
                ['field' => 'unknown', 'rules' => ['email']],
            ],
        ]]);
        FakeDataAgent::fake([[
            'fields' => [
                ['field' => 'name', 'formatter' => 'company'],
                ['field' => 'delay_days', 'formatter' => 'system("rm")'],
            ],
        ]]);
        $assistant = app(Assistant::class);

        $rules = $assistant->validationRules($this->model())->value;
        $this->assertSame(['max:100', 'regex:/^[A-Z]/', 'min:2'], $rules['name'], 'AI max replaces the heuristic one; unsafe rules are dropped.');
        $this->assertSame(['min:0', 'max:365'], $rules['delay_days']);
        $this->assertArrayNotHasKey('unknown', $rules);

        $formatters = $assistant->fakeFormatters($this->model())->value;
        $this->assertSame('company', $formatters['name']);
        $this->assertSame('integer', $formatters['delay_days'], 'Unknown formatters keep the rule-based choice.');
        $this->assertStringStartsWith('mb_substr(fake()->company()', FakeData::expression($this->model()->fields()['name'], 'company'));
    }

    public function test_definition_command_designs_an_entity_with_ai(): void
    {
        $this->enableAi();
        $directory = base_path('Modules/GeneratorAiFixture');
        $this->assertDirectoryDoesNotExist($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/module.json', json_encode(['name' => 'GeneratorAiFixture', 'alias' => 'generatoraifixture', 'providers' => [], 'files' => []]));
        EntityDesignAgent::fake([[
            'modelName' => 'Ticket', 'tableName' => 'generator_ai_fixture_tickets',
            'fields' => [
                ['name' => 'subject', 'type' => 'string', 'nullable' => false, 'unique' => false, 'length' => 160, 'enumValues' => [], 'label' => 'Objet'],
                ['name' => 'priority', 'type' => 'enum', 'nullable' => false, 'unique' => false, 'length' => null, 'enumValues' => ['low', 'high'], 'label' => 'Priorité'],
            ],
            'relations' => [],
        ]]);

        try {
            $status = Artisan::call('larastarterkit:definition', ['module' => 'GeneratorAiFixture', '--describe' => 'Tickets de support avec un objet et une priorité.', '--soft-deletes' => true, '--no-interaction' => true]);
            $this->assertSame(0, $status, Artisan::output());
            $model = json_decode(File::get($directory.'/module.json'), true)['models']['ticket'];
            $this->assertSame('generator_ai_fixture_tickets', $model['tableName']);
            $this->assertSame(['low', 'high'], $model['fillable'][1]['enumValues']);
            $this->assertSame('Objet', $model['fillable'][0]['label']);
            $this->assertTrue($model['softDeletes'], 'Extra options survive the save.');
            EntityDesignAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Tickets de support'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_an_unusable_ai_design_reports_the_ai_failure(): void
    {
        $this->enableAi();
        EntityDesignAgent::fake([[
            'modelName' => 'Ticket', 'tableName' => 'tickets', 'relations' => [],
            'fields' => [['name' => 'created_at', 'type' => 'datetime', 'nullable' => true, 'unique' => false, 'length' => null, 'enumValues' => [], 'label' => 'Créé']],
        ]]);

        try {
            app(Assistant::class)->designEntity('Support', 'Tickets de support avec un objet.');
            $this->fail('The unusable design should be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("L'IA n'a pas fourni de conception exploitable", $e->getMessage());
            $this->assertStringContainsString('aucun champ exploitable', $e->getMessage());
        }
    }
}
