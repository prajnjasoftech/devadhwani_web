<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TempleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temple_name' => $this->temple_name,
            'temple_code' => $this->temple_code,
            'contact_person_name' => $this->contact_person_name,
            'contact_number' => $this->contact_number,
            'alternate_contact_number' => $this->alternate_contact_number,
            'email' => $this->email,
            'address' => $this->address,
            'district' => $this->district,
            'place' => $this->place,
            'image' => $this->image,
            'image_url' => $this->getFileUrl($this->image),
            'id_proof_type' => $this->id_proof_type,
            'id_proof_number' => $this->id_proof_number,
            'id_proof_file' => $this->id_proof_file,
            'id_proof_file_url' => $this->getFileUrl($this->id_proof_file),
            'status' => $this->status,
            'users_count' => $this->whenCounted('users'),
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
