<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temple_id' => $this->temple_id,
            'role_name' => $this->role_name,
            'description' => $this->description,
            'is_system_role' => $this->is_system_role,
            'permissions' => $this->whenLoaded('permissions', fn() => $this->permissions->map(fn($p) => [
                'id' => $p->id,
                'module_key' => $p->module_key,
                'module_name' => $p->module_name,
                'action' => $p->action,
            ])),
            'permission_ids' => $this->whenLoaded('permissions', fn() => $this->permissions->pluck('id')),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
