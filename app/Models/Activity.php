<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History of every action on a booking, with the staff member who did it (shown on the booking page and in staff reports).
 */
#[Fillable(['reservation_id', 'user_id', 'action', 'details', 'amount'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'reserved' => 'Reserved',
        'edited' => 'Changed booking',
        'checked_in' => 'Checked in',
        'checked_out' => 'Checked out',
        'payment' => 'Received payment',
        'deposit' => 'Received deposit',
        'refund' => 'Gave refund',
        'charge' => 'Added to bill',
        'charge_removed' => 'Removed from bill',
        'cancelled' => 'Cancelled',
        'no_show' => 'Marked no-show',
        'deleted' => 'Deleted booking',
        'restored' => 'Restored booking',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    /** Record what the logged-in staff member just did. */
    public static function log(?Reservation $reservation, string $action, ?string $details = null, $amount = null): self
    {
        return static::create([
            'reservation_id' => $reservation?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'details' => $details !== null ? mb_substr($details, 0, 300) : null,
            'amount' => $amount,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withTrashed();
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }
}
