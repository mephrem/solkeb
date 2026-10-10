<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample bookings for training and demos:
 *  - 10 rooms occupied since 3 days ago (29 Sep when run on 2 Oct): 4 check out today, 6 in two days (4 Oct)
 *  - 20 rooms reserved: two organisation bookings (3 rooms and 2 rooms) and single bookings,
 *    arriving today, tomorrow and later; some reserve a room right after its current guest leaves
 *  - work spread over 4 receptionists, so "who did what" and the staff report have something to show
 *
 * Run:  php artisan db:seed --class=SampleBookingsSeeder
 * It removes existing bookings, guests and payments first (rooms, prices, staff and settings are kept).
 */
class SampleBookingsSeeder extends Seeder
{
    private int $guestNo = 0;

    private const NAMES = [
        ['Abdirahman', 'Mohamed', 'Ali'], ['Hodan', 'Ahmed', 'Warsame'], ['Dawit', 'Tesfaye', 'Bekele'], ['Fardowsa', 'Omar', 'Yusuf'],
        ['Liya', 'Getachew', 'Alemu'], ['Mustafe', 'Abdillahi', 'Nur'], ['Sara', 'Kebede', 'Tadesse'], ['Ibrahim', 'Farah', 'Hassan'],
        ['Hawa', 'Ismail', 'Aden'], ['Yonas', 'Girma', 'Haile'], ['Amina', 'Hassan', 'Abdi'], ['Samuel', 'Mekonnen', 'Desta'],
        ['Khadar', 'Jama', 'Osman'], ['Meron', 'Tadesse', 'Wolde'], ['Ayan', 'Abdullahi', 'Musa'], ['Biniam', 'Solomon', 'Tekle'],
        ['Nimco', 'Said', 'Elmi'], ['Yared', 'Fikru', 'Mulugeta'], ['Deeqa', 'Hirsi', 'Farah'], ['Tewodros', 'Abebe', 'Kassa'],
        ['Halima', 'Sheikh', 'Ahmed'], ['Mahlet', 'Yohannes', 'Girma'], ['Abdi', 'Musa', 'Adan'], ['Selam', 'Worku', 'Lemma'],
        ['Faisal', 'Ali', 'Gure'], ['Ruth', 'Haile', 'Mariam'], ['Omar', 'Dahir', 'Farah'], ['Hanna', 'Desalegn', 'Bekele'],
        ['Zakaria', 'Hussein', 'Nur'], ['Tsion', 'Mesfin', 'Ayele'], ['Ahmed', 'Yasin', 'Hersi'], ['Kidist', 'Alemayehu', 'Tola'],
    ];

    public function run(): void
    {
        // Never on the live system: training / demo data only
        if (app()->environment('production')) {
            $this->command?->error('Sample data is for training and testing only. It does not run on a production installation (APP_ENV=production).');

            return;
        }

        if (Reservation::withTrashed()->exists()
            && ! $this->command->confirm('This removes all existing bookings, guests and payments and creates sample ones. Continue?', false)) {
            $this->command->warn('Nothing changed.');

            return;
        }

        $this->clear();

        $staff = $this->staff();
        [$hodan, $sara, $abdi, $liya] = $staff;
        $today = today();

        $rooms = Room::with('roomType')->where('status', '!=', 'out_of_order')->orderBy('floor')->orderBy('number')->get();
        if ($rooms->count() < 12) {
            $this->command->error('At least 12 rooms in service are needed for the sample (found '.$rooms->count().').');

            return;
        }

        // 10 occupied rooms (spread over the floors), the rest free today
        $occupied = $rooms->filter(fn ($r, $i) => $i % 5 < 2 || $i < 2)->take(10)->values();
        if ($occupied->count() < 10) {
            $occupied = $occupied->merge($rooms->diff($occupied)->take(10 - $occupied->count()))->values();
        }
        $free = $rooms->diff($occupied)->values();

        $since = $today->copy()->subDays(3);                // 29 Sep when run on 2 Oct
        $endingToday = $occupied->take(4);
        $endingLater = $occupied->slice(4);

        // ---------------- Occupied: 10 rooms checked in 3 days ago ----------------
        foreach ($occupied as $i => $room) {
            $out = $i < 4 ? $today->copy() : $today->copy()->addDays(2); // today, or 4 Oct
            $company = [6 => 'Ethio Telecom', 8 => 'WFP Jijiga'][$i] ?? null;
            $r = $this->book($staff[$i % 4], [$room], $since, $out, $since->copy()->subDays($i % 3)->setTime(9 + $i, 15), $company);
            $this->checkIn($r, $staff[($i + 1) % 4], $since->copy()->setTime(13 + ($i % 5), 10 * ($i % 5)));
            $this->addServices($r, $staff[($i + 2) % 4], $i);

            // Payments: a deposit at booking, then a payment by someone else on a later day.
            // Guests leaving today: three settled in full, one still owes. Guests staying on: part paid.
            $total = $r->totalCharges();
            if ($i % 3 === 0) {
                $this->pay($r, $staff[$i % 4], 'deposit', round($room->rate()), $i % 2 ? 'cash' : 'telebirr', $r->created_at);
            }
            $due = round($total - $r->totalPaid(), 2);
            if ($i < 3) {
                $this->pay($r, $staff[($i + 3) % 4], 'payment', $due, ['cash', 'telebirr', 'cbe_birr'][$i], $today->copy()->setTime(8, 20 + $i * 7));
            } elseif ($i >= 4 && $i % 2 === 0) {
                $this->pay($r, $staff[($i + 3) % 4], 'payment', round($due / 2), $i % 4 ? 'bank_transfer' : 'cash', $since->copy()->addDay()->setTime(10, 5));
            }
        }

        // ---------------- Reserved: 20 rooms ----------------
        $pick = fn (int $n) => $free->splice(0, $n);
        $at = fn (int $daysAgo, int $h) => $today->copy()->subDays($daysAgo)->setTime($h, 30);

        // Organisation booking of 3 rooms, arriving today (shows as Reserved on the board in one colour)
        $unicef = $this->book($sara, $pick(3)->all(), $today, $today->copy()->addDays(3), $at(5, 10), 'UNICEF Jijiga', 'phone');
        $this->pay($unicef, $sara, 'deposit', 4500, 'bank_transfer', $at(5, 11));

        // Organisation booking of 2 rooms, arriving tomorrow
        $this->book($abdi, $pick(2)->all(), $today->copy()->addDay(), $today->copy()->addDays(3), $at(2, 15), 'Save the Children', 'corporate');

        // Single bookings arriving today (Reserved on the board)
        foreach ($pick(4) as $k => $room) {
            $r = $this->book($staff[$k % 4], [$room], $today, $today->copy()->addDays(1 + $k % 3), $at(1 + $k, 9 + $k), null, ['phone', 'walk_in', 'booking_com', 'phone'][$k]);
            if ($k % 2 === 0) {
                $this->pay($r, $staff[($k + 1) % 4], 'deposit', round($room->rate()), 'telebirr', $at(1 + $k, 10 + $k));
            }
        }

        // Tomorrow and later (Available today, with "Next:" on the board)
        foreach ($pick(3) as $k => $room) {
            $this->book($staff[($k + 2) % 4], [$room], $today->copy()->addDay(), $today->copy()->addDays(3 + $k), $at(1, 14 + $k), null, 'phone');
        }
        foreach ($free->take(3) as $k => $room) {     // any further rooms stay free
            $in = $today->copy()->addDays(3 + $k);
            $this->book($staff[$k % 4], [$room], $in, $in->copy()->addDays(2), $at(0, 8 + $k), null, 'walk_in');
        }

        // Rooms reserved right after the current guest leaves
        foreach ($endingToday->take(2) as $k => $room) {   // new guest arrives the day the old one checks out
            $this->book($liya, [$room], $today, $today->copy()->addDays(2), $at(3, 16 + $k), null, 'phone');
        }
        foreach ($endingLater->take(3) as $k => $room) {   // arrives on 4 Oct, when the room is free
            $in = $today->copy()->addDays(2 + $k % 2);
            $this->book($hodan, [$room], $in, $in->copy()->addDays(2), $at(2, 9 + $k), null, 'booking_com');
        }

        // One room waiting to be cleaned
        $free->first()?->update(['housekeeping_status' => 'dirty']);

        $reserved = DB::table('reservation_room')->join('reservations', 'reservations.id', '=', 'reservation_room.reservation_id')
            ->where('reservations.status', 'confirmed')->count();
        $this->command->info("Sample created: {$occupied->count()} rooms occupied (4 leave today, 6 on ".$today->copy()->addDays(2)->format('d M')."), {$reserved} rooms reserved, 2 group bookings.");
        $this->command->info('Sample receptionists (password: password): hodan@, sara@, abdi@, liya@solkebhotel.com');
    }

    private function clear(): void
    {
        foreach (Guest::all() as $g) {
            Storage::disk('local')->deleteDirectory("ids/{$g->id}");
        }
        DB::table('activities')->delete();
        DB::table('payments')->delete();
        DB::table('charges')->delete();
        DB::table('reservation_room')->delete();
        DB::table('reservations')->delete();
        DB::table('guests')->delete();
        Room::where('status', 'occupied')->update(['status' => 'available']);
        Room::query()->update(['housekeeping_status' => 'clean']);
    }

    /** The four receptionists the owner described (created if they don't exist yet). */
    private function staff(): array
    {
        return collect([['Hodan Ali', 'hodan'], ['Sara Getachew', 'sara'], ['Abdi Omar', 'abdi'], ['Liya Bekele', 'liya']])
            ->map(fn ($s) => User::firstOrCreate(['email' => "{$s[1]}@solkebhotel.com"],
                ['name' => $s[0], 'password' => 'password', 'role' => 'receptionist', 'is_active' => true]))
            ->all();
    }

    private function newGuest(?string $company = null): Guest
    {
        $n = self::NAMES[$this->guestNo % count(self::NAMES)];
        $no = ++$this->guestNo;

        return Guest::create([
            'first_name' => $n[0], 'father_name' => $n[1], 'grandfather_name' => $n[2],
            'phone' => '09'.str_pad((string) (11000000 + $no * 7919), 8, '0', STR_PAD_LEFT),
            'nationality' => 'Ethiopia', 'company' => $company,
            'tin' => $company ? str_pad((string) (100000000 + $no * 104729), 10, '0', STR_PAD_LEFT) : null,
        ]);
    }

    private function book(User $by, array $rooms, Carbon $in, Carbon $out, Carbon $when, ?string $company = null, string $source = 'walk_in'): Reservation
    {
        $guest = $this->newGuest($company);
        $prefix = Setting::get('booking_prefix', 'SLK').'-'.$when->format('ymd').'-';
        $last = Reservation::withTrashed()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');

        $r = Reservation::create([
            'code' => $prefix.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT),
            'guest_id' => $guest->id, 'check_in_date' => $in, 'check_out_date' => $out,
            'adults' => count($rooms), 'source' => $company ? 'corporate' : $source,
            'status' => 'confirmed', 'created_by' => $by->id,
        ]);
        $r->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
        foreach ($rooms as $room) {
            $r->rooms()->attach($room->id, ['nightly_rate' => $room->rate()]);
        }
        $r->assignGroupColor();
        $this->log($r, $by, 'reserved', 'Room '.collect($rooms)->pluck('number')->join(', ').", {$in->format('d M')} – {$out->format('d M Y')}", null, $when);

        return $r;
    }

    private function checkIn(Reservation $r, User $by, Carbon $when): void
    {
        $names = [];
        foreach ($r->rooms()->with('roomType')->get() as $room) {
            $guest = $r->guest;
            $guest->update(['id_type' => 'national_id', 'id_number' => 'FAN-'.str_pad((string) ($guest->id * 3571 % 100000), 5, '0', STR_PAD_LEFT)]);
            $this->sampleIdPhotos($guest);
            $r->rooms()->updateExistingPivot($room->id, ['guest_id' => $guest->id]);
            $room->update(['status' => 'occupied']);
            $nights = $r->nights();
            $r->charges()->create([
                'room_id' => $room->id, 'type' => 'room', 'description' => "Room {$room->number} ({$room->roomType->name})",
                'quantity' => $nights, 'unit_price' => $room->pivot->nightly_rate, 'amount' => $nights * $room->pivot->nightly_rate,
                'charge_date' => $when->toDateString(), 'created_by' => $by->id,
            ]);
            $names[] = "Room {$room->number}: {$guest->full_name}";
        }
        $r->forceFill(['status' => 'checked_in', 'checked_in_at' => $when, 'checked_in_by' => $by->id])->save();
        $this->log($r, $by, 'checked_in', implode('; ', $names), null, $when);
    }

    private function addServices(Reservation $r, User $by, int $i): void
    {
        $breakfast = Service::where('name', 'Breakfast')->first();
        $laundry = Service::where('name', 'like', 'Laundry%')->first();
        foreach (array_filter([$i % 2 === 0 ? [$breakfast, 2] : null, $i % 3 === 1 ? [$laundry, 4] : null]) as [$service, $qty]) {
            if (! $service) {
                continue;
            }
            $when = $r->check_in_date->copy()->addDay()->setTime(9, 0);
            $c = $r->charges()->create([
                'service_id' => $service->id, 'type' => 'service', 'description' => $service->name, 'quantity' => $qty,
                'unit_price' => $service->price, 'amount' => $qty * $service->price, 'charge_date' => $when->toDateString(), 'created_by' => $by->id,
            ]);
            $this->log($r, $by, 'charge', "{$service->name} × {$qty}", $c->amount, $when);
        }
    }

    private function pay(Reservation $r, User $by, string $type, float $amount, string $method, Carbon $when): void
    {
        if ($amount <= 0) {
            return;
        }
        $ref = $method === 'cash' ? null : strtoupper(substr($method, 0, 2)).random_int(100000, 999999);
        Payment::create([
            'reservation_id' => $r->id, 'amount' => $amount, 'method' => $method, 'reference' => $ref,
            'type' => $type, 'paid_at' => $when, 'received_by' => $by->id,
        ]);
        $this->log($r, $by, $type, Payment::METHODS[$method].($ref ? ' · '.$ref : ''), $amount, $when);
    }

    private function log(Reservation $r, User $by, string $action, ?string $details, $amount, Carbon $when): void
    {
        $a = Activity::create(['reservation_id' => $r->id, 'user_id' => $by->id, 'action' => $action, 'details' => $details, 'amount' => $amount]);
        $a->forceFill(['created_at' => $when])->save();
    }

    /** Clearly-marked placeholder ID images, so the check-in records look complete. */
    private function sampleIdPhotos(Guest $guest): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }
        foreach (['front' => 'id_front_path', 'back' => 'id_back_path'] as $side => $column) {
            $img = imagecreatetruecolor(640, 400);
            imagefill($img, 0, 0, imagecolorallocate($img, 224, 242, 254));
            $ink = imagecolorallocate($img, 3, 105, 161);
            imagerectangle($img, 12, 12, 627, 387, $ink);
            imagestring($img, 5, 40, 40, 'SAMPLE ID - '.strtoupper($side), $ink);
            imagestring($img, 4, 40, 90, $guest->full_name, $ink);
            imagestring($img, 4, 40, 120, $guest->id_number ?? '', $ink);
            imagestring($img, 3, 40, 340, 'Training data - not a real document', $ink);
            ob_start();
            imagejpeg($img, null, 80);
            $path = "ids/{$guest->id}/sample-{$side}.jpg";
            Storage::disk('local')->put($path, ob_get_clean());
            imagedestroy($img);
            $guest->{$column} = $path;
        }
        $guest->save();
    }
}
