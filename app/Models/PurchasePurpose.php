<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePurpose extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'purpose_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
