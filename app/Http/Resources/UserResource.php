<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temple_id' => $this->temple_id,
            'user_type' => $this->user_type,
            'name' => $this->name,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'address' => $this->address,
            'role_id' => $this->role_id,
            'role' => $this->whenLoaded('role', fn() => new RoleResource($this->role)),
            'temple' => $this->whenLoaded('temple', fn() => new TempleResource($this->temple)),
            'must_reset_password' => $this->must_reset_password,
            'is_active' => $this->is_active,
            'profile_image' => $this->profile_image,
            'profile_image_url' => $this->getFileUrl($this->profile_image),
            'id_proof_type' => $this->id_proof_type,
            'id_proof_number' => $this->id_proof_number,
            'id_proof_file' => $this->id_proof_file,
            'id_proof_file_url' => $this->getFileUrl($this->id_proof_file),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'permissions' => $this->when($request->routeIs('auth.me'), fn() => $this->getPermissions()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    private function getFileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
