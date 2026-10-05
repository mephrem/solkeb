<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received on a booking (payment / deposit) or given back (refund), with the account it went into.
 */
#[Fillable(['reservation_id', 'amount', 'currency', 'method', 'payment_account_id', 'reference', 'payer_name', 'type', 'paid_at', 'received_by', 'notes'])]
class Payment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'telebirr' => 'telebirr',
        'cbe_birr' => 'CBE Birr',
        'bank_transfer' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
        'card' => 'Card',
        'other' => 'Other',
    ];

    /** Methods that need a transaction number. */
    public const NEEDS_REFERENCE = ['telebirr', 'cbe_birr', 'bank_transfer', 'mobile_money'];

    /** Hide lines that belong to a deleted booking. Use withoutGlobalScope('live') to see them (deleted log). */
    protected static function booted(): void
    {
        static::addGlobalScope('live', fn ($q) => $q->whereHas('reservation'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    /** Where the money went: "Cash", or the bank / wallet account. */
    public function channel(): string
    {
        return $this->account ? $this->account->label() : (self::METHODS[$this->method] ?? $this->method);
    }

    /** For the history log: "CBE · 1000… · Sisay · FT123 · paid by Kader" */
    public function describe(): string
    {
        return $this->channel().($this->reference ? ' · '.$this->reference : '').($this->payer_name ? ' · paid by '.$this->payer_name : '');
    }
}
