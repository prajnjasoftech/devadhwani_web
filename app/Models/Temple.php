<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temple extends Model
{
    use HasFactory;

    protected $fillable = [
        'temple_name',
        'temple_code',
        'contact_person_name',
        'contact_number',
        'alternate_contact_number',
        'email',
        'address',
        'district',
        'place',
        'image',
        'id_proof_type',
        'id_proof_number',
        'id_proof_file',
        'status',
        'accounts_setup_completed',
    ];

    protected $casts = [
        'status' => 'string',
        'id_proof_type' => 'string',
        'accounts_setup_completed' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('temple_name', 'like', "%{$search}%")
              ->orWhere('temple_code', 'like', "%{$search}%")
              ->orWhere('contact_person_name', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%")
              ->orWhere('district', 'like', "%{$search}%")
              ->orWhere('place', 'like', "%{$search}%");
        });
    }

    public static function generateTempleCode(): string
    {
        $lastTemple = self::orderBy('id', 'desc')->first();
        $nextId = $lastTemple ? $lastTemple->id + 1 : 1;
        return 'TMP' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }
}
