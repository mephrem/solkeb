<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'guest_id', 'check_in_date', 'check_out_date', 'checked_in_at', 'checked_out_at',
    'adults', 'children', 'status', 'source', 'special_requests', 'created_by', 'deleted_by', 'delete_reason',
    'checked_in_by', 'checked_out_by', 'cancelled_by', 'group_color',
    'organization_id', 'on_credit', 'credit_invoice_no', 'credit_invoiced_at',
])]
/**
 * A booking: one guest (the contact), one or more rooms, the bill (charges) and payments.
 *
 * Life cycle: confirmed → checked_in → checked_out, or confirmed → cancelled / no_show.
 * Each room can hold its own guest (reservation_room.guest_id), set at check-in for group bookings.
 */
class Reservation extends Model
{
    use SoftDeletes; // "deleted" bookings stay in the Deleted bookings log

    public const CONFIRMED = 'confirmed';

    public const CHECKED_IN = 'checked_in';

    public const CHECKED_OUT = 'checked_out';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    /** Not arrived yet: can still be changed, checked in, cancelled or marked no-show. */
    public const UPCOMING = ['pending', self::CONFIRMED];

    /** Holding rooms: upcoming or in house. */
    public const ACTIVE = ['pending', self::CONFIRMED, self::CHECKED_IN];

    /** Finished one way or another: the bill is closed. */
    public const CLOSED = [self::CHECKED_OUT, self::CANCELLED, self::NO_SHOW];

    public const SOURCES = [
        'walk_in' => 'Walk-in', 'phone' => 'Phone', 'website' => 'Website',
        'booking_com' => 'Booking.com', 'agent' => 'Travel agent', 'corporate' => 'Corporate / NGO',
    ];

    /** Colours for bookings with several rooms (checked to stay distinguishable, also for colour-blind staff). */
    public const GROUP_COLORS = ['#7c3aed', '#ea580c', '#0d9488', '#be123c', '#4338ca', '#a16207', '#db2777', '#4d7c0f'];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'deleted_at' => 'datetime',
            'on_credit' => 'boolean',
            'credit_invoiced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Give every booking a readable code: SLK-260930-0001
        static::creating(function (Reservation $reservation) {
            if (empty($reservation->code)) {
                $prefix = Setting::get('booking_prefix', 'SLK').'-'.now()->format('ymd').'-';
                $last = static::withTrashed()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
                $next = $last ? ((int) substr($last, -4)) + 1 : 1;
                $reservation->code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** For a guest in house: 'today' when they are due out today, 'overdue' when the date has passed. */
    public function departureState(): ?string
    {
        if ($this->status !== 'checked_in') {
            return null;
        }

        return $this->check_out_date->isToday() ? 'today' : ($this->check_out_date->isPast() ? 'overdue' : null);
    }

    /** "Leaves today" / "Overdue 2 days" */
    public function departureLabel(): ?string
    {
        if ($this->departureState() === 'today') {
            $now = now()->format('H:i');
            if ($this->hasHalfDay()) {
                return $now <= self::halfDayUntil() ? 'Leaves by '.self::halfDayUntil().' (half day)' : 'Half day ended '.self::halfDayUntil();
            }

            return $now > self::checkOutTime() ? 'Check-out time passed ('.self::checkOutTime().')' : 'Leaves today';
        }

        return $this->departureState() === 'overdue'
            ? 'Overdue '.($d = (int) $this->check_out_date->diffInDays(today())).' day'.($d === 1 ? '' : 's')
            : null;
    }

    /** The hotel's check-out time (Hotel settings), e.g. "11:00". */
    public static function checkOutTime(): string
    {
        return Setting::get('check_out_time') ?: '11:00';
    }

    /** Until when a half-day extension lets the guest stay on the check-out day, e.g. "14:30". */
    public static function halfDayUntil(): string
    {
        return Setting::get('half_day_until') ?: '14:30';
    }

    /** Share of one night's price charged for a half-day extension (percent). */
    public static function halfDayPercent(): float
    {
        $p = Setting::get('half_day_percent');

        return $p === null || $p === '' ? 50.0 : (float) $p;
    }

    /** Ids of the rooms that already have a half-day extension on this bill. */
    public function halfDayRoomIds()
    {
        return $this->charges()->where('type', 'half_day')->pluck('room_id');
    }

    /** Every room on the booking has a half-day extension. */
    public function hasHalfDay(): bool
    {
        $rooms = $this->relationLoaded('rooms') ? $this->rooms->pluck('id') : $this->rooms()->pluck('rooms.id');

        return $rooms->isNotEmpty() && $rooms->diff($this->halfDayRoomIds())->isEmpty();
    }

    /** Give a credit stay its invoice number when the guest leaves: CI-2026-0001 */
    public function issueCreditInvoice(): void
    {
        if ($this->credit_invoice_no) {
            return;
        }
        $prefix = 'CI-'.now()->format('Y').'-';
        $last = static::withTrashed()->where('credit_invoice_no', 'like', $prefix.'%')->orderByDesc('credit_invoice_no')->value('credit_invoice_no');
        $this->credit_invoice_no = $prefix.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
        $this->credit_invoiced_at = now();
        $this->save();
    }

    /** When the organisation should have paid a credit invoice by. */
    public function creditDueDate()
    {
        return $this->credit_invoiced_at?->copy()->addDays($this->organization?->credit_days ?? 30);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)
            ->withPivot('nightly_rate', 'guest_id')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at')->latest('id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Organisation name for a group booking, else the guest's name. */
    public function groupName(): string
    {
        return $this->organization?->name ?: ($this->guest->company ?: $this->guest->full_name);
    }

    /**
     * Give a booking with 2+ rooms a colour that no other group in the hotel at the same time has.
     * One-room bookings have no colour.
     */
    public function assignGroupColor(): void
    {
        $count = $this->rooms()->count();
        if ($count < 2) {
            if ($this->group_color) {
                $this->forceFill(['group_color' => null])->save();
            }

            return;
        }
        if ($this->group_color) {
            return;
        }

        $taken = static::whereKeyNot($this->id)
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->whereNotNull('group_color')
            ->where('check_in_date', '<', $this->check_out_date)
            ->where('check_out_date', '>', $this->check_in_date)
            ->pluck('group_color')->all();
        $free = array_values(array_diff(self::GROUP_COLORS, $taken));
        $this->forceFill(['group_color' => $free[0] ?? self::GROUP_COLORS[$this->id % count(self::GROUP_COLORS)]])->save();
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /** Nights booked (at least one). */
    public function nights(): int
    {
        return max(1, (int) $this->check_in_date->diffInDays($this->check_out_date));
    }

    /** Nights actually stayed if the guest leaves today (at least one): used at check-out to re-bill the room. */
    public function nightsSoFar(): int
    {
        return max(1, (int) $this->check_in_date->diffInDays(today()));
    }

    public function isUpcoming(): bool
    {
        return in_array($this->status, self::UPCOMING, true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    /** An organisation that pays later (credit invoice + payment request letter). */
    public function billedOnCredit(): bool
    {
        return $this->on_credit && $this->organization_id !== null;
    }

    public function totalCharges(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    /** Payments and deposits minus refunds. */
    public function totalPaid(): float
    {
        $paid = (float) $this->payments()->whereIn('type', ['deposit', 'payment'])->sum('amount');
        $refunded = (float) $this->payments()->where('type', 'refund')->sum('amount');

        return $paid - $refunded;
    }

    /**
     * What the guest owes if they check out today: room nights counted to today
     * (fewer if they leave early, more if they stayed on), the same way check-out re-bills them.
     */
    public function dueIfLeavingToday(): float
    {
        if ($this->status !== 'checked_in') {
            return $this->balance();
        }
        $nights = $this->nightsSoFar();
        $rooms = $this->charges()->where('type', 'room')->get();
        $roomNow = $rooms->sum(fn ($c) => round($this->roomLineNights($c, $nights) * (float) $c->unit_price, 2));

        return round($this->totalCharges() - (float) $rooms->sum('amount') + $roomNow - $this->totalPaid(), 2);
    }

    /**
     * How many nights a room line covers when the whole stay is $stayNights long.
     *
     * A line for a room the guest has moved out of is finished and keeps its nights. A line for a room
     * still on the booking runs to the end of the stay: the stay's nights minus those billed before it.
     */
    public function roomLineNights(Charge $line, int $stayNights): int
    {
        $current = $this->relationLoaded('rooms') ? $this->rooms->pluck('id') : $this->rooms()->pluck('rooms.id');

        return $current->contains($line->room_id)
            ? max(0, $stayNights - (int) $line->nights_before)
            : (int) $line->quantity;
    }

    public function balance(): float
    {
        return round($this->totalCharges() - $this->totalPaid(), 2);
    }

    public function scopeArrivingOn($query, $date)
    {
        return $query->whereDate('check_in_date', $date)->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeDepartingOn($query, $date)
    {
        return $query->whereDate('check_out_date', $date)->where('status', 'checked_in');
    }
}
