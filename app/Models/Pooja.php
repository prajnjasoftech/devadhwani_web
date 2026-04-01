<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pooja extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'deity_id',
        'name',
        'description',
        'frequency',
        'next_pooja_date',
        'amount',
        'devotee_required',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'devotee_required' => 'boolean',
        'is_active' => 'boolean',
        'next_pooja_date' => 'date',
    ];

    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function deity(): BelongsTo
    {
        return $this->belongsTo(Deity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhereHas('deity', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'once' => 'One Time',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            default => ucfirst($this->frequency),
        };
    }
}
