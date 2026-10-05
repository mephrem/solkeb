<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'first_name', 'father_name', 'grandfather_name', 'gender', 'phone', 'email',
    'nationality', 'id_type', 'id_number', 'id_front_path', 'id_back_path', 'city', 'company', 'tin', 'is_vip', 'is_blacklisted', 'notes',
])]
/**
 * A guest record. Repeat guests are matched by phone number (booking) or ID number (check-in) so they are not duplicated.
 */
class Guest extends Model
{
    public const ID_TYPES = [
        'kebele_id' => 'Kebele ID', 'national_id' => 'National ID (Fayda)', 'passport' => 'Passport',
        'driving_license' => 'Driving licence', 'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function hasIdPhotos(): bool
    {
        return $this->id_front_path && $this->id_back_path;
    }

    /** Any booking history: as the booker, or as the guest staying in a room of someone else's booking. */
    public function hasHistory(): bool
    {
        return $this->reservations()->withTrashed()->exists()
            || DB::table('reservation_room')->where('guest_id', $this->id)->exists();
    }

    /** "Abdi Mohamed Ahmed" */
    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->father_name, $this->grandfather_name])));
    }
}
