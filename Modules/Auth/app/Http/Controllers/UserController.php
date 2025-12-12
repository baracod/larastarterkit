<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Http\Requests\UserRequest;
use App\Http\Middleware\ConvertRequestToSnakeCase;
use Baracod\Larastarterkit\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
  public function __construct()
  {
  }

  public function index()
  {
    return User::all();
  }

  public function show($id)
  {
    return User::findOrFail($id);
  }

  public function store(UserRequest $request)
  {
    $validated = $request->validated();

    if (User::where('email', $validated['email'])->exists()) {
      return ApiResponse::error('Email already exists', 422);
    }

    return User::create($validated);
  }

  public function update(UserRequest $request, User $user)
  {
    $validated = $request->validated();
    $user->update($validated);

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
    $request->validate([
      'user_id' => 'required|exists:auth_users,id',
      'new_password' => 'required|string|min:8',
      'new_password_confirmation' => 'required|string|same:new_password',
    ]);

    if ($request->new_password !== $request->new_password_confirmation)
      return ApiResponse::error('Les deux mots de passe ne correspondent pas');

    $user = User::findOrFail($request->input('user_id'));
    $user->password = bcrypt($request->input('new_password'));
    $user->save();

    return ApiResponse::success($user);
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
      return ApiResponse::error('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
  }

  public function updateProfile(UserRequest $request, $id)
  {
    try {
      $user = User::findOrFail($id);
      // Ne contient que les champs validés (déjà filtrés par ton FormRequest)
      $data = $request->validated();

      return DB::transaction(function () use ($request, $user, $data) {
        // 1) Avatar
        if ($request->input('avatar_file') instanceof \Illuminate\Http\UploadedFile) {
          // Uploade d'abord le nouveau
          $newPath = $request->input('avatar_file')->store('avatars', 'public');

          // Puis supprime l'ancien si existant
          if (!empty($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
          }

          // Mets à jour la colonne avatar
          $user->avatar = $newPath;

          // Retire le champ fichier des données à mass-assigner
          unset($data['avatar_file']);
        }

        // 2) Password (si envoyé)
        if (array_key_exists('password', $data) && filled($data['password'])) {
          $user->password = Hash::make($data['password']);
          unset($data['password']); // évite mass-assign si non fillable
        }

        // 3) Champs booléens / cast
        if (array_key_exists('active', $data)) {
          $user->active = (bool) $data['active'];
          unset($data['active']);
        }

        // 4) Timestamp email vérifié (optionnel)
        if (array_key_exists('email_verified_at', $data) && filled($data['email_verified_at'])) {
          // Si ta colonne est DATETIME/TIMESTAMP, tu peux laisser la string ISO ;
          // sinon: $user->email_verified_at = Carbon::parse($data['email_verified_at']);
          $user->email_verified_at = $data['email_verified_at'];
          unset($data['email_verified_at']);
        }

        // 5) Mass-assign du reste (assure-toi que les colonnes sont dans $fillable)
        $user->fill($data);

        $user->save();

        return ApiResponse::success($user->fresh(), 'Profile updated successfully.');
      });
    } catch (ModelNotFoundException $e) {
      return ApiResponse::notFound('User not found.');
    } catch (\Throwable $e) {
      return ApiResponse::error('An unexpected error occurred: ' . $e->getMessage(), 500);
    }
  }

  public function suspendOrActive($id)
  {
    try {
      $user = User::findOrFail($id);
      $user->active = !$user->active; // Toggle active status
      if ($user->active)
        $user->tokens()->delete();
      $user->save();

      $status = $user->active ? 'activated' : 'suspended';
      return ApiResponse::success($user, "User has been {$status} successfully.");
    } catch (ModelNotFoundException $e) {
      return ApiResponse::notFound('User not found.');
    } catch (\Exception $e) {
      return ApiResponse::error('An unexpected error occurred: ' . $e->getMessage(), 500);
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
      $user->tokens()->delete(); // Invalide les tokens existants
      $user->save();
    }

    return ApiResponse::success($users, 'Users reactivated successfully.');
  }
}
