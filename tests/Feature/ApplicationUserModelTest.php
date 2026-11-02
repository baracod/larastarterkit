<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Larastarterkit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Tests\Fixtures\ApplicationUser;
use Tests\TestCase;

class ApplicationUserModelTest extends TestCase
{
    public function test_core_resolves_the_configured_user_model(): void
    {
        $this->assertSame(User::class, Larastarterkit::userModel());

        config(['auth.providers.users.model' => ApplicationUser::class]);

        $this->assertSame(ApplicationUser::class, Larastarterkit::userModel());
        $this->assertInstanceOf(ApplicationUser::class, Larastarterkit::newUser());
    }

    public function test_user_management_returns_the_application_model(): void
    {
        config(['auth.providers.users.model' => ApplicationUser::class]);
        $administrator = User::factory()->create(['password' => 'password', 'active' => true, 'must_change_password' => false]);
        $administrator->roles()->attach(Role::where('name', 'administrator')->firstOrFail());
        Sanctum::actingAs(ApplicationUser::findOrFail($administrator->id));

        $this->getJson("/api/v1/auth/users/{$administrator->id}")
            ->assertOk()
            ->assertJsonPath('application_marker', 'from-application');

        $this->getJson('/api/v1/auth/users')
            ->assertOk()
            ->assertJsonPath('0.application_marker', 'from-application');

        $this->assertInstanceOf(ApplicationUser::class, Role::where('name', 'administrator')->firstOrFail()->users()->first());
    }

    public function test_application_model_keeps_the_stored_morph_type(): void
    {
        config(['auth.providers.users.model' => ApplicationUser::class]);
        $this->beforeApplicationDestroyed(fn () => Relation::morphMap([], false));
        Larastarterkit::registerUserMorphAlias();

        $user = ApplicationUser::findOrFail(User::factory()->create()->id);

        $this->assertSame(User::class, $user->getMorphClass());
        $this->assertSame(ApplicationUser::class, Relation::getMorphedModel(User::class));
    }

    public function test_a_model_that_does_not_extend_the_core_user_is_refused(): void
    {
        config(['auth.providers.users.model' => \stdClass::class]);

        $this->expectException(LogicException::class);

        Larastarterkit::userModel();
    }
}
