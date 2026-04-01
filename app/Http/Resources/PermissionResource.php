<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_key' => $this->module_key,
            'module_name' => $this->module_name,
            'action' => $this->action,
            'key' => $this->key,
        ];
    }
}
