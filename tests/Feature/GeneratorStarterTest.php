<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Generator\Backend\Database\MigrationGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\Support\ModuleAutoload;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Tests\TestCase;

class GeneratorStarterTest extends TestCase
{
    private const MODULE = 'GeneratorFixture';

    private const TABLE = 'generator_fixture_entries';

    private string $directory;

    public function beginDatabaseTransaction()
    {
        $this->directory = base_path('Modules/'.self::MODULE);
        $this->assertDirectoryDoesNotExist($this->directory);
        config(['generator.register_autoload' => false]);

        // Module minimal : même structure de routes que le stub des nouveaux modules.
        File::ensureDirectoryExists($this->directory.'/routes');
        File::put($this->directory.'/module.json', json_encode(['name' => self::MODULE, 'alias' => 'generatorfixture', 'providers' => [], 'files' => []], JSON_THROW_ON_ERROR));
        File::put($this->directory.'/routes/api.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::middleware(['auth:sanctum'])\n    ->prefix('v1/generatorfixture')\n    ->group(function () {\n        //{{ next-route }}\n    });\n");

        // Définition sans IA puis migration générée, exécutée hors transaction (DDL, notamment sur MariaDB).
        $status = Artisan::call('larastarterkit:definition', [
            'module' => self::MODULE, 'model' => 'Entry', '--table' => self::TABLE, '--no-ai' => true, '--no-interaction' => true,
            '--fields' => 'name:string:120:unique, status:enum(draft|published):default=draft, price:float:nullable, is_active:boolean, published_on:date:nullable, notes:text:nullable',
        ]);
        $this->assertSame(0, $status, Artisan::output());
        $store = DefinitionStore::fromFile($this->directory.'/module.json');
        $migration = (new MigrationGen($store->module()->model('entry')))->generate();
        $this->assertSame('generated', $migration['status'], $migration['message']);
        (require $migration['path'])->up();

        $connection = Schema::getConnection();
        $pdo = $connection->getPdo();
        parent::beginDatabaseTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection, $pdo): void {
            // RefreshDatabase a déjà annulé la transaction et fermé la connexion.
            $connection->setPdo($pdo);
            $connection->getSchemaBuilder()->dropIfExists(self::TABLE);
            $connection->disconnect();
            File::deleteDirectory($this->directory);
        });
    }

    public function test_definition_is_generated_into_a_working_fullstack_crud_without_ai(): void
    {
        $status = Artisan::call('larastarterkit:crud', ['module' => self::MODULE, 'modelKey' => 'entry', '--batch' => true, '--no-ai' => true]);
        $this->assertSame(0, $status, Artisan::output());

        foreach ([
            'app/Models/Entry.php', 'app/Http/Requests/EntryRequest.php', 'app/Http/Controllers/EntryController.php',
            'database/factories/EntryFactory.php', 'database/seeders/EntrySeeder.php', 'database/seeders/GeneratedPermissionSeeder.php',
            'tests/Feature/EntryApiTest.php', 'resources/ts/types/entities.d.ts', 'resources/ts/api/Entry.ts',
            'resources/ts/pages/entries/index.vue', 'resources/ts/components/GeneratorFixtureEntryAddOrEdit.vue', 'resources/ts/menuItems.json',
        ] as $file) {
            $this->assertFileExists($this->directory.'/'.$file);
        }
        // La migration existante n'est ni dupliquée ni réécrite.
        $this->assertCount(1, File::glob($this->directory.'/database/migrations/*.php'));

        // Tous les indicateurs sont conservés (les générateurs ne s'écrasent plus mutuellement).
        $model = DefinitionStore::fromFile($this->directory.'/module.json')->module()->model('entry');
        foreach (['hasMigration', 'hasModel', 'hasRequest', 'hasController', 'hasRoute', 'hasPermission', 'hasFactory', 'hasTest'] as $flag) {
            $this->assertTrue($model->backend()->{$flag}, $flag);
        }
        $this->assertSame('api/generatorfixture/entries', $model->backend()->apiRoute);
        $this->assertTrue($model->frontend()->hasLang);

        // i18n : clés "field" en camelCase, libellés du dictionnaire sans IA.
        $fr = json_decode(File::get($this->directory.'/resources/ts/locales/fr.json'), true, 512, JSON_THROW_ON_ERROR)['entry'];
        $this->assertSame('Nom', $fr['field']['name']);
        $this->assertSame('Actif', $fr['field']['isActive']);
        $this->assertSame('Entries', $fr['titlePlural']);
        $en = json_decode(File::get($this->directory.'/resources/ts/locales/en.json'), true, 512, JSON_THROW_ON_ERROR)['entry'];
        $this->assertSame('Entries', $en['titlePlural']);

        $menu = json_decode(File::get($this->directory.'/resources/ts/menuItems.json'), true, 512, JSON_THROW_ON_ERROR)[0];
        $this->assertSame(['name' => 'generatorfixture-entries'], $menu['to']);
        $this->assertSame(['browse', self::TABLE], [$menu['action'], $menu['subject']]);

        $types = File::get($this->directory.'/resources/ts/types/entities.d.ts');
        $this->assertStringContainsString("status: 'draft' | 'published'", $types);
        $this->assertStringContainsString('price?: number | null', $types);
        $form = File::get($this->directory.'/resources/ts/components/GeneratorFixtureEntryAddOrEdit.vue');
        $this->assertStringContainsString("{ name: '', status: 'draft', price: null, is_active: false, published_on: null, notes: null }", $form);
        $this->assertStringContainsString('<VSwitch', $form);
        $this->assertStringContainsString('/bulk-delete', File::get($this->directory.'/resources/ts/api/Entry.ts'));
        $this->assertStringContainsString("action: 'browse'", File::get($this->directory.'/resources/ts/pages/entries/index.vue'));

        $routes = File::get($this->directory.'/routes/api.php');
        $this->assertLessThan(strpos($routes, "apiResource('entries'"), strpos($routes, "'entries/bulk-delete'"));
        $this->assertMatchesRegularExpression('#//\s*\{\{ next-route \}\}#', $routes, 'The marker survives formatting.');

        // Une deuxième exécution ne duplique rien (via l'ancien nom de commande, toujours accepté).
        $this->assertSame(0, Artisan::call('generate:crud', ['module' => self::MODULE, 'modelKey' => 'entry', '--batch' => true, '--no-ai' => true, '--modes' => 'route,menu']));
        $this->assertSame(1, substr_count(File::get($this->directory.'/routes/api.php'), "apiResource('entries'"));
        $this->assertCount(1, json_decode(File::get($this->directory.'/resources/ts/menuItems.json'), true));

        // Régénérer la factory ne réécrit pas le modèle : le code ajouté à la main est conservé.
        $modelPath = $this->directory.'/app/Models/Entry.php';
        File::put($modelPath, str_replace('protected $table', "public const CUSTOM_MARKER = true;\n\n    protected \$table", File::get($modelPath)));
        $this->assertSame(0, Artisan::call('larastarterkit:crud', ['module' => self::MODULE, 'modelKey' => 'entry', '--batch' => true, '--no-ai' => true, '--force' => true, '--modes' => 'factory']));
        $this->assertStringContainsString('CUSTOM_MARKER', File::get($modelPath));
        $this->assertSame(1, substr_count(File::get($modelPath), 'function newFactory'));

        $this->exerciseGeneratedApi();
    }

    public function test_unknown_modes_and_missing_arguments_are_rejected(): void
    {
        $this->assertSame(1, Artisan::call('larastarterkit:crud', ['module' => self::MODULE, 'modelKey' => 'entry', '--modes' => 'model,bogus', '--batch' => true]));
        $this->assertStringContainsString('bogus', Artisan::output());
        $this->assertSame(1, Artisan::call('larastarterkit:crud', ['--batch' => true]));
        $this->assertSame(1, Artisan::call('larastarterkit:definition', ['module' => self::MODULE, 'model' => 'Other', '--describe' => 'Des fournisseurs avec un nom.', '--no-ai' => true, '--no-interaction' => true]));
        $this->assertStringContainsString('syntaxe', Artisan::output());
        $this->assertSame(1, Artisan::call('larastarterkit:definition', ['module' => self::MODULE, 'model' => 'Entry', '--fields' => 'name:string', '--no-interaction' => true]));
        $this->assertStringContainsString('existe déjà', Artisan::output());
    }

    private function exerciseGeneratedApi(): void
    {
        ModuleAutoload::registerForCurrentProcess(self::MODULE);
        Route::middleware('api')->prefix('api')->group($this->directory.'/routes/api.php');
        $endpoint = '/api/v1/generatorfixture/entries';
        $entry = \Modules\GeneratorFixture\Models\Entry::factory()->create(['name' => 'Before']);
        $this->assertTrue(in_array('status', array_keys(\Modules\GeneratorFixture\Models\Entry::factory()->raw()), true));

        $this->getJson($endpoint)->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson($endpoint)->assertForbidden();
        $this->postJson($endpoint.'/bulk-delete', ['ids' => [$entry->id]])->assertForbidden();

        $permissions = Permission::where('subject', self::TABLE);
        $this->assertSame(4, $permissions->count());
        $this->assertTrue(Permission::where('key', 'access_generatorfixture')->exists());
        $role = Role::create(['name' => 'fixture-editor', 'display_name' => 'Fixture editor']);
        $user->roles()->attach($role);
        $role->permissions()->sync(Permission::where('subject', self::TABLE)->whereIn('action', ['browse', 'add', 'edit'])->pluck('id'));

        $valid = ['name' => 'Created', 'status' => 'published', 'price' => 12.5, 'is_active' => true];
        $created = $this->postJson($endpoint, $valid)->assertCreated()->json();
        $this->postJson($endpoint, $valid)->assertUnprocessable()->assertJsonPath('errors.name.key', 'unique');
        $this->postJson($endpoint, [...$valid, 'name' => 'Other', 'status' => 'archived'])->assertUnprocessable()->assertJsonPath('errors.status.key', 'in');
        $this->postJson($endpoint, ['status' => 'draft'])->assertUnprocessable()->assertJsonPath('errors.name.key', 'required');
        // Mettre à jour un enregistrement sans changer son nom unique reste possible.
        $this->putJson($endpoint.'/'.$created['id'], [...$valid, 'price' => 20])->assertOk()->assertJsonPath('price', 20);
        $this->putJson($endpoint.'/999999', [...$valid, 'name' => 'Missing'])->assertNotFound();

        $this->getJson($endpoint.'?search=Creat')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $created['id']);
        $this->getJson($endpoint.'?per_page=1&sort=name&direction=asc')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.name', 'Before');

        $this->deleteJson($endpoint.'/'.$entry->id)->assertForbidden();
        $role->permissions()->attach(Permission::where('subject', self::TABLE)->where('action', 'delete')->value('id'));
        $this->postJson($endpoint.'/bulk-delete', ['ids' => ['x']])->assertUnprocessable();
        $this->postJson($endpoint.'/bulk-delete', ['ids' => [$entry->id, $created['id']]])->assertOk()->assertJsonPath('deleted', 2);
        $this->assertDatabaseCount(self::TABLE, 0);
    }
}
