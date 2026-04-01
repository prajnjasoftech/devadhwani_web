<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_item_id',
        'devotee_id',
        'name',
        'nakshathra_id',
        'gothram',
        'notes',
    ];

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function devotee(): BelongsTo
    {
        return $this->belongsTo(Devotee::class);
    }

    public function nakshathra(): BelongsTo
    {
        return $this->belongsTo(Nakshathra::class);
    }
}
