<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
/**
 * A staff account. Every action on a booking is recorded with the user who did it.
 *
 * Roles: admin (everything), manager (front desk + setup + reports), receptionist (front desk),
 * accountant (payments, refunds, reports). Pages are limited by the "role" route middleware.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public const RECEPTIONIST = 'receptionist';

    public const ACCOUNTANT = 'accountant';

    public const ROLES = [self::ADMIN, self::MANAGER, self::RECEPTIONIST, self::ACCOUNTANT];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Bookings, payments or charges this person handled. Such accounts are disabled, not deleted. */
    public function hasHistory(): bool
    {
        return Reservation::withTrashed()->where(fn ($q) => $q->where('created_by', $this->id)->orWhere('deleted_by', $this->id)
            ->orWhere('checked_in_by', $this->id)->orWhere('checked_out_by', $this->id)->orWhere('cancelled_by', $this->id))->exists()
            || Activity::where('user_id', $this->id)->exists()
            || Payment::withoutGlobalScope('live')->where('received_by', $this->id)->exists()
            || Charge::withoutGlobalScope('live')->where('created_by', $this->id)->exists();
    }

    public function reservationsCreated(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by');
    }
}
