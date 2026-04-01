<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DeityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'temple_id' => $this->temple_id,
            'name' => $this->name,
            'sanskrit_name' => $this->sanskrit_name,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->getImageUrl(),
            'deity_type' => $this->deity_type,
            'deity_type_label' => $this->getDeityTypeLabel(),
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    private function getImageUrl(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    private function getDeityTypeLabel(): string
    {
        return match ($this->deity_type) {
            'main' => 'Main Deity',
            'sub' => 'Sub Deity',
            'upadevata' => 'Upadevata',
            default => ucfirst($this->deity_type),
        };
    }
}
