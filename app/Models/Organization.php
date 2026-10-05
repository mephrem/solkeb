<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A company, NGO or government office that books rooms for its people. */
#[Fillable(['name', 'contact_person', 'contact_title', 'phone', 'email', 'address', 'tin', 'credit_allowed', 'credit_days', 'notes', 'is_active'])]
class Organization extends Model
{
    protected function casts(): array
    {
        return ['credit_allowed' => 'boolean', 'is_active' => 'boolean', 'credit_days' => 'integer'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Credit stays that still have money owing (finished stays first, oldest first). */
    public function unpaidCredit()
    {
        return $this->reservations()->where('on_credit', true)->whereIn('status', ['checked_in', 'checked_out'])
            ->with(['guest', 'rooms'])->orderByRaw("status = 'checked_in'")->orderBy('check_out_date')->get()
            ->filter(fn (Reservation $r) => $r->balance() > 0.009)->values();
    }
}
