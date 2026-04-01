<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'temple_id',
        'user_type',
        'name',
        'contact_number',
        'email',
        'address',
        'role_id',
        'password',
        'must_reset_password',
        'is_active',
        'profile_image',
        'id_proof_type',
        'id_proof_number',
        'id_proof_file',
        'last_login_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_reset_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('temple', function (Builder $builder) {
            $user = auth()->user();

            if ($user && $user->user_type === 'temple_user' && $user->temple_id) {
                $builder->where(function ($query) use ($user) {
                    $query->where('temple_id', $user->temple_id)
                          ->orWhere('id', $user->id);
                });
            }
        });
    }

    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->user_type === 'platform_admin';
    }

    public function isTempleUser(): bool
    {
        return $this->user_type === 'temple_user';
    }

    public function hasPermission(string $moduleKey, string $action): bool
    {
        if ($this->isPlatformAdmin()) {
            // Platform admins can only manage temples, not users or roles
            return in_array($moduleKey, ['dashboard', 'temples']);
        }

        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($moduleKey, $action);
    }

    public function getPermissions(): array
    {
        if ($this->isPlatformAdmin()) {
            // Platform admins only get temple-related permissions
            return Permission::whereIn('module_key', ['dashboard', 'temples'])
                ->get()
                ->map(function ($permission) {
                    return $permission->key;
                })->toArray();
        }

        if (!$this->role) {
            return [];
        }

        return $this->role->permissions->map(function ($permission) {
            return $permission->key;
        })->toArray();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTempleUsers($query)
    {
        return $query->where('user_type', 'temple_user');
    }

    public function scopePlatformAdmins($query)
    {
        return $query->where('user_type', 'platform_admin');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
