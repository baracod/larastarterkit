<?php

namespace Modules\Auth\Http\Controllers;


use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Http\Resources\UserResource;
use Baracod\Larastarterkit\Helper\ApiResponse;
use Illuminate\Queue\Attributes\WithoutRelations;
use Baracod\Larastarterkit\Http\Controllers\Controller;

class AuthController extends Controller
{


  /**
   * Login user and create token
   *
   * @param  [string] email
   * @param  [string] password
   * @param  [boolean] remember_me
   */

  public function login(Request $request)
  {

    $request->validate([
      'email' => 'required|string|email',
      'password' => 'required|string',
      'remember_me' => 'boolean'
    ]);

    $credentials = request(['email', 'password']);

    if (!Auth::attempt($credentials, $request->remember_me ?? false)) {
      return ApiResponse::unauthorized('Unauthorized');
    }


    $user = User::with(["roles"])->where('id', $request->user()->id)->first();
    if (!$user->active) {
      return ApiResponse::locked('User is inactive, contact the administrator');
    }

    $tokenResult = $user->createToken('Personal Access Token');
    $token = $tokenResult->plainTextToken;

    $abilityRulers = $user->permissions()->get([
      'auth_permissions.id',
      'auth_permissions.key',
      'auth_permissions.action',
      'auth_permissions.subject',
    ])->map(function ($permission) {
      return $permission->only(['id', 'action', 'subject']);
    });

    $abilityRulers = $abilityRulers->toArray();



    $abilityRulers = array_values($abilityRulers);
    $permissions = $user->permissions()->get();
    $roles = $user->roles()->get();



    return ApiResponse::success([
      'user' => $request->user()->withoutRelations()->toArray(),
      'access_token' => $token,
      'token_type' => 'Bearer',
      'ability_rules' => $abilityRulers,
      'permissions' => $permissions,
      'roles' => $roles,
    ]);
  }

  /**
   * Get the authenticated User
   *
   * @return [json] user object
   */
  public function user(Request $request)
  {
    return ApiResponse::success($request->user());
  }
  /**
   * Logout user (Revoke the token)
   *
   * @return [string] message
   */
  public function logout(Request $request)
  {
    $user =  $request->user();
    $user->tokens()->delete();

    return ApiResponse::success([
      'message' => 'Successfully logged out'
    ]);
  }

  /**
   * update user password
   * @param Request $request
   * @return [string] message
   *
   */
  public function updatePassword(Request $request)
  {

    $request->validate([
      'new_password' => 'required|string',
      'new_password_confirmation' => 'required|string|same:new_password',
    ]);

    if ($request->new_password !== $request->new_password_confirmation)
      return ApiResponse::error('Les deux mots de passe ne correspondent pas');


    $user = $request->user();
    $user->password = bcrypt($request->new_password);
    $user->save();
    return ApiResponse::success(
      $user
    );
  }

  public function forgottenPassword(Request $request)
  {
    $request->validate([
      'email' => 'required|string|email',
    ]);

    $user = User::where('email', $request->email)->firstOrFail();
    $token = app(abstract: 'auth.password.broker')->createToken($user);
    $user->sendPasswordResetNotification($token);


    // Here you would typically initiate the password reset process,
    // such as sending a reset link to the user's email.

    return ApiResponse::success([
      'message' => 'If your email exists in our system, you will receive a password reset link shortly.'
    ]);
  }

  public function resetPassword(Request $request)
  {

    $tokenData = DB::table('auth_password_reset_tokens')
      ->where('email', $request->email)
      ->first();

    $request->validate([
      'token' => 'required|string',
      'email' => 'required|string|email|exists:auth_users,email',
      'new_password' => 'required|string',
      'new_password_confirmation' => 'required|string|same:new_password',
    ]);

    $tokenData = DB::table('auth_password_reset_tokens')
      ->where('email', $request->email)
      ->first();

    if (!$tokenData || !Hash::check($request->token, $tokenData->token)) {
      ApiResponse::error('Token invalide ou expiré', 400);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $user->password = bcrypt($request->new_password);
    $user->save();

    return ApiResponse::success([
      'message' => 'Password has been reset successfully.'
    ]);
  }
}
