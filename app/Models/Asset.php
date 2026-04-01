<?php

namespace App\Models;

use App\Traits\BelongsToTemple;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory, BelongsToTemple;

    protected $fillable = [
        'temple_id',
        'asset_number',
        'asset_type_id',
        'name',
        'description',
        'quantity',
        'estimated_value',
        'acquisition_date',
        'acquisition_type',
        'donation_id',
        'location',
        'condition',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'estimated_value' => 'decimal:2',
        'acquisition_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Asset $asset) {
            if (empty($asset->asset_number)) {
                $asset->asset_number = static::generateAssetNumber($asset->temple_id);
            }
        });
    }

    public static function generateAssetNumber(int $templeId): string
    {
        $temple = Temple::find($templeId);
        $prefix = $temple ? $temple->temple_code : 'AST';
        $year = date('Y');

        $lastAsset = static::where('temple_id', $templeId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;

        return sprintf('%s/AST/%s/%04d', $prefix, $year, $sequence);
    }

    // Relationships
    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
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
            $q->where('asset_number', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('location', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getEstimatedValueFormattedAttribute(): string
    {
        return $this->estimated_value ? '₹' . number_format($this->estimated_value, 2) : '-';
    }

    public function getQuantityWithUnitAttribute(): string
    {
        $qty = number_format($this->quantity, 3);
        if ($this->assetType && $this->assetType->unit) {
            return $qty . ' ' . $this->assetType->unit;
        }
        return $qty;
    }

    public function getConditionBadgeClassAttribute(): string
    {
        return match ($this->condition) {
            'excellent' => 'bg-green-100 text-green-800',
            'good' => 'bg-blue-100 text-blue-800',
            'fair' => 'bg-yellow-100 text-yellow-800',
            'poor' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getAcquisitionTypeLabelAttribute(): string
    {
        return match ($this->acquisition_type) {
            'existing' => 'Existing Asset',
            'donation' => 'From Donation',
            'purchase' => 'Purchased',
            default => ucfirst($this->acquisition_type),
        };
    }
}
