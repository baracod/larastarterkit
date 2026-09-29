<?php

namespace Tests\Unit;

use Baracod\Larastarterkit\Core\Http\Middleware\EnsureAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureAbilityTest extends TestCase
{
    public function test_it_rejects_an_unauthenticated_request(): void
    {
        try {
            (new EnsureAbility)->handle(new Request, fn () => new JsonResponse, 'edit', 'auth_users');
            $this->fail('An unauthenticated request should have been rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
        }
    }

    public function test_it_rejects_a_user_without_the_required_ability(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('hasRole')->with('administrator')->willReturn(false);
        $user->expects($this->once())->method('can')->with('edit', 'auth_users')->willReturn(false);

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        try {
            (new EnsureAbility)->handle($request, fn () => new JsonResponse, 'edit', 'auth_users');
            $this->fail('A request without the required ability should have been rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_it_allows_a_user_with_the_required_ability(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('hasRole')->with('administrator')->willReturn(false);
        $user->expects($this->once())->method('can')->with('edit', 'auth_users')->willReturn(true);

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureAbility)->handle(
            $request,
            fn () => new JsonResponse(['allowed' => true]),
            'edit',
            'auth_users'
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_allows_an_administrator_without_checking_an_ability(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('hasRole')->with('administrator')->willReturn(true);
        $user->expects($this->never())->method('can');

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureAbility)->handle(
            $request,
            fn () => new JsonResponse(['allowed' => true]),
            'edit',
            'auth_users'
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
