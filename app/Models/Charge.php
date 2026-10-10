<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a guest's bill: room nights (type "room"), a charge, or a discount (negative amount).
 *
 * Room lines: one per room per part of the stay. A room move ends one part and starts another;
 * nights_before = nights of the stay billed before this part began (0 for the room checked into).
 */
#[Fillable([
    'reservation_id', 'room_id', 'service_id', 'type', 'description',
    'quantity', 'nights_before', 'unit_price', 'amount', 'charge_date', 'created_by',
])]
class Charge extends Model
{
    /** Hide lines that belong to a deleted booking. Use withoutGlobalScope('live') to see them (deleted log). */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'charge_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Hide lines that belong to a deleted booking. Use withoutGlobalScope('live') to see them (deleted log).
        static::addGlobalScope('live', fn ($q) => $q->whereHas('reservation'));

        // Keep amount in sync with quantity x unit price unless set explicitly
        static::saving(function (Charge $charge) {
            if ($charge->amount === null) {
                $charge->amount = round((float) $charge->quantity * (float) $charge->unit_price, 2);
            }
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
