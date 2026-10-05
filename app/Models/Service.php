<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An item from the old price list (Setup → Services). Kept for older bills and the revenue breakdown.
 */
#[Fillable(['name', 'category', 'price', 'is_active'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
