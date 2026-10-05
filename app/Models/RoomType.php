<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of room (Standard, Twin, Suite...) with its base nightly price.
 */
#[Fillable(['name', 'description', 'max_adults', 'max_children', 'base_rate', 'amenities', 'is_active'])]
class RoomType extends Model
{
    protected function casts(): array
    {
        return [
            'base_rate' => 'decimal:2',
            'amenities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
