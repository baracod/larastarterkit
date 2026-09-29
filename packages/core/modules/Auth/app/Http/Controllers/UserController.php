<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Baracod\Larastarterkit\Core\Http\Middleware\ConvertRequestToSnakeCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Http\Requests\UserRequest;
use Modules\Auth\Models\User;
use Modules\Auth\Notifications\LoginInstructions;
use Modules\Auth\Services\PasswordService;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(ConvertRequestToSnakeCase::class);
    }

    public function index()
    {
        return User::with('roles')->get();
    }

    public function show($id)
    {
        $authUser = auth()->user();

        // Vérifier si l'utilisateur peut accéder à ce profil
        // Soit c'est son propre profil, soit il a le rôle admin
        if ($authUser->id != $id && ! $authUser->hasRole('administrator')) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à accéder à ce profil.',
            ], 403);
        }

        $user = User::with('roles')->findOrFail($id);
        $user->setAttribute('modules', ['settings' => $user->moduleSettings()->get(['module', 'key', 'value'])]);

        return $user;
    }

    public function store(UserRequest $request)
    {
        $validated = $request->validated();

        if (User::where('email', $validated['email'])->exists()) {
            return ApiResponse::error('Email already exists', 422);
        }

        $emailLocale = $validated['email_locale'] ?? null;
        unset($validated['email_locale']);
        $user = User::create($validated);
        $user->notify(new LoginInstructions($emailLocale));

        return $user;
    }

    public function sendLoginInstructions(Request $request, int $id)
    {
        abort_unless(auth()->user()->hasRole('administrator'), 403);
        $user = User::findOrFail($id);
        $data = $request->validate(['email_locale' => 'sometimes|string|in:fr,en']);
        $user->notify(new LoginInstructions($data['email_locale'] ?? null));

        return ApiResponse::success(null, __('auth::account_mail.instructions_queued'));
    }

    public function update(UserRequest $request, User $user)
    {
        $validated = $request->validated();
        $user->update($validated);

        if ($user->wasChanged('active') && ! $user->active) {
            $user->tokens()->delete();
        }

        return $user;
    }

    public function destroy($id)
    {
        $model = User::findOrFail($id);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->all();

        $model = User::whereIn('id', $ids);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function changePassword(Request $request)
    {
        // Déléguer à UserSecurityController via ForceChangePasswordRequest
        // Cette méthode est conservée pour rétrocompatibilité mais redirige vers le service
        Gate::authorize('forcePasswordReset', User::class);

        $validated = $request->validate((new \Modules\Auth\Http\Requests\ForceChangePasswordRequest)->rules());
        $validated['must_change_password'] = true;
        app(PasswordService::class)->forceChangePassword($request->user(), $validated);

        return ApiResponse::success(null, 'Mot de passe réinitialisé avec succès.');
    }

    public function setRolesToUser(Request $request, int $id)
    {
        try {
            $data = $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'exists:auth_roles,id',
            ]);

            $user = User::findOrFail($id);
            $user->roles()->sync($data['roles']);

            return ApiResponse::success($user, 'Roles updated successfully.');
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('User not found.');
        } catch (\Exception $e) {
            Log::error('Failed to assign roles to user.', [
                'user_id' => $id,
                'exception' => $e,
            ]);

            return ApiResponse::error('An unexpected error occurred.', 500);
        }
    }

    public function updateProfile(UserRequest $request, $id)
    {
        try {
            $authUser = auth()->user();

            // Vérifier si l'utilisateur peut modifier ce profil
            // Soit c'est son propre profil, soit il a le rôle admin
            if ($authUser->id != $id && ! $authUser->hasRole('administrator')) {
                return response()->json([
                    'message' => 'Vous n\'êtes pas autorisé à modifier ce profil.',
                ], 403);
            }

            $user = User::findOrFail($id);
            // Ne contient que les champs validés (déjà filtrés par ton FormRequest)
            $data = $request->validated();

            return DB::transaction(function () use ($request, $user, $data) {
                $oldAvatarPath = $user->getRawOriginal('avatar');

                if ($request->hasFile('avatar_file')) {
                    $newPath = $request->file('avatar_file')->store('avatars', 'public');

                    if (! is_string($newPath) || $newPath === '') {
                        throw new \RuntimeException('Unable to store the profile image.');
                    }

                    $user->avatar = $newPath;
                    unset($data['avatar_file']);
                }

                $user->fill($data);

                $user->save();

                if (isset($newPath) && is_string($oldAvatarPath) && $oldAvatarPath !== '' && $oldAvatarPath !== $newPath) {
                    Storage::disk('public')->delete($oldAvatarPath);
                }

                return ApiResponse::success($user->fresh()->toArray(), 'Profile updated successfully.');
            });
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('User not found.');
        } catch (\Throwable $e) {
            Log::error('Failed to update user profile.', [
                'user_id' => $id,
                'exception' => $e,
            ]);

            return ApiResponse::error('An unexpected error occurred.', 500);
        }
    }

    public function suspendOrActive($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->active = ! $user->active; // Toggle active status
            if (! $user->active) {
                $user->tokens()->delete();
            }
            $user->save();

            $status = $user->active ? 'activated' : 'suspended';

            return ApiResponse::success($user, "User has been {$status} successfully.");
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('User not found.');
        } catch (\Exception $e) {
            Log::error('Failed to change user active status.', [
                'user_id' => $id,
                'exception' => $e,
            ]);

            return ApiResponse::error('An unexpected error occurred.', 500);
        }
    }

    public function suspendMultiple(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:auth_users,id',
        ]);

        $users = User::whereIn('id', $request->input('user_ids'))->get();

        foreach ($users as $user) {
            $user->active = false;
            $user->tokens()->delete();
            $user->save();
        }

        return ApiResponse::success($users, 'Users updated successfully.');
    }

    public function reactivateMultiple(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:auth_users,id',
        ]);

        $users = User::whereIn('id', $request->input('user_ids'))->get();

        foreach ($users as $user) {
            $user->active = true;
            $user->save();
        }

        return ApiResponse::success($users, 'Users reactivated successfully.');
    }
}
