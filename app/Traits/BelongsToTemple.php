<?php

namespace App\Traits;

use App\Models\Temple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTemple
{
    protected static function bootBelongsToTemple(): void
    {
        static::addGlobalScope('temple', function (Builder $builder) {
            $user = Auth::user();

            if ($user && $user->user_type === 'temple_user' && $user->temple_id) {
                $builder->where('temple_id', $user->temple_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();

            if ($user && $user->user_type === 'temple_user' && $user->temple_id && !$model->temple_id) {
                $model->temple_id = $user->temple_id;
            }
        });
    }

    public function temple(): BelongsTo
    {
        return $this->belongsTo(Temple::class);
    }

    public function scopeForTemple(Builder $query, int $templeId): Builder
    {
        return $query->where('temple_id', $templeId);
    }
}
