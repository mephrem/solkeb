<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A hotel room. Its price comes from its room type unless the room has its own price (rate_override).
 */
#[Fillable(['room_type_id', 'number', 'floor', 'status', 'housekeeping_status', 'rate_override', 'notes'])]
class Room extends Model
{
    protected function casts(): array
    {
        return ['rate_override' => 'decimal:2'];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class)
            ->withPivot('nightly_rate', 'guest_id')
            ->withTimestamps();
    }

    /** The nightly rate for this room: its own override, or its type's base rate. */
    public function rate(): float
    {
        return (float) ($this->rate_override ?? $this->roomType->base_rate);
    }

    /**
     * Rooms free for the whole stay [checkIn, checkOut).
     * A booking checking out on the day another checks in does not clash.
     * A guest still in the room after their check-out date (overdue) keeps it until they are checked out.
     */
    public function scopeAvailableBetween(Builder $query, string $checkIn, string $checkOut, ?int $exceptReservationId = null): Builder
    {
        $fromToday = Carbon::parse($checkIn)->lte(today());

        return $query
            ->where('status', '!=', 'out_of_order')
            ->whereDoesntHave('reservations', function (Builder $q) use ($checkIn, $checkOut, $exceptReservationId, $fromToday) {
                $q->whereIn('status', Reservation::ACTIVE)
                    ->when($exceptReservationId, fn ($w) => $w->where('reservations.id', '!=', $exceptReservationId))
                    ->where('check_in_date', '<', $checkOut)
                    ->where(fn ($w) => $w->where('check_out_date', '>', $checkIn)
                        ->when($fromToday, fn ($o) => $o->orWhere('reservations.status', 'checked_in')));
            });
    }
}
