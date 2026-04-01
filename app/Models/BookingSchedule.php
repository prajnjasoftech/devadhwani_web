<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_item_id',
        'scheduled_date',
        'status',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scheduled_date', $date);
    }

    public function scopeForTemple($query, $templeId)
    {
        return $query->whereHas('bookingItem.booking', function ($q) use ($templeId) {
            $q->where('temple_id', $templeId);
        });
    }

    // Methods
    public function markCompleted(?string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    public function markCancelled(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $reason,
        ]);
    }
}
