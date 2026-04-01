<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_key',
        'module_name',
        'action',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
                    ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->module_name} - " . ucfirst($this->action);
    }

    public function getKeyAttribute(): string
    {
        return "{$this->module_key}.{$this->action}";
    }

    public static function getGroupedByModule(): array
    {
        return self::all()
            ->groupBy('module_key')
            ->map(function ($permissions, $moduleKey) {
                $first = $permissions->first();
                return [
                    'module_key' => $moduleKey,
                    'module_name' => $first->module_name,
                    'permissions' => $permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'action' => $permission->action,
                            'key' => $permission->key,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }
}
