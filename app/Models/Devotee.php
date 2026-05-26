<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Devotee extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'name',
        'nakshathra_id',
        'gothram',
    ];

    // Mutators - Auto Title Case for names
    protected function name(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn ($value) => $value ? mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8') : $value,
        );
    }

    // Relationships
    public function nakshathra(): BelongsTo
    {
        return $this->belongsTo(Nakshathra::class);
    }

    public function bookingBeneficiaries(): HasMany
    {
        return $this->hasMany(BookingBeneficiary::class);
    }

    public function bookingItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            BookingItem::class,
            BookingBeneficiary::class,
            'devotee_id',      // Foreign key on booking_beneficiaries
            'id',              // Foreign key on booking_items
            'id',              // Local key on devotees
            'booking_item_id'  // Local key on booking_beneficiaries
        );
    }

    // Scopes
    public function scopeSearch($query, ?string $term)
    {
        if ($term && strlen($term) >= 3) {
            return $query->where('name', 'like', $term . '%');
        }
        return $query;
    }
}
