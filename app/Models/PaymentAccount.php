<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bank account or mobile wallet the hotel receives money into. */
#[Fillable(['kind', 'provider', 'account_name', 'account_number', 'notes', 'is_active'])]
class PaymentAccount extends Model
{
    public const KINDS = ['bank' => 'Bank account', 'wallet' => 'Mobile wallet'];

    /** Suggestions for the provider field; any other name can be typed. */
    public const PROVIDERS = [
        'bank' => ['Commercial Bank of Ethiopia (CBE)', 'Cooperative Bank of Oromia', 'Bank of Abyssinia', 'Awash Bank',
            'Dashen Bank', 'Hibret Bank', 'Wegagen Bank', 'Shabelle Bank', 'Hijra Bank', 'Rammis Bank', 'Siinqee Bank'],
        'wallet' => ['telebirr', 'CBE Birr', 'E-birr', 'Coopay-Ebirr', 'Kaafi', 'M-Pesa', 'HelloCash', 'Amole'],
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The one account printed under "Please pay to" on credit invoices and payment request letters:
     * the one the admin chose, or else the first active bank account.
     */
    public static function forInvoices(): ?self
    {
        return static::active()->find(Setting::get('invoice_account_id'))
            ?? static::active()->orderByRaw("kind = 'wallet'")->orderBy('id')->first();
    }

    public function isInvoiceAccount(): bool
    {
        return static::forInvoices()?->id === $this->id;
    }

    /** The payment method a payment into this account is reported under. */
    public function method(): string
    {
        return $this->kind === 'wallet' ? 'mobile_money' : 'bank_transfer';
    }

    /** "CBE · 1000503354938 · Sisay Ketema" */
    public function label(): string
    {
        return "{$this->provider} · {$this->account_number} · {$this->account_name}";
    }
}
