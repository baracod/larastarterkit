<?php

namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    // Optionnel: retirer le wrapper "data"
    // public static $wrap = null;

    public function toArray($request): array
    {
        // Assure-toi de charger la relation avant (->load('roles'))
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'additionalInfo' => $this->additional_info,
            'avatar' => $this->avatar,      // brut (ou URL si tu as gardé l'accessor sur avatar)
            'avatarUrl' => $this->when(isset($this->avatar_url), $this->avatar_url),
            'emailVerifiedAt' => optional($this->email_verified_at)?->toISOString(),
            'active' => (bool) $this->active,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),

            // Accessor déjà présent côté modèle
            'roleNames' => $this->when(isset($this->role_names), $this->role_names),

            // Relation roles (chargée)
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'displayName' => $role->display_name,
                        'description' => $role->description,
                        'order' => $role->order,
                        'isOwner' => (bool) ($role->is_owner ?? 0),
                        'createdAt' => optional($role->created_at)?->toISOString(),
                        'updatedAt' => optional($role->updated_at)?->toISOString(),
                        'pivot' => $role->pivot ? [
                            'userId' => $role->pivot->user_id,
                            'roleId' => $role->pivot->role_id,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
