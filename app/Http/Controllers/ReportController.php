<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reports: revenue and occupancy (with CSV export), the night audit, the staff report and each person's own "My activity".
 */
class ReportController extends Controller
{
    /** Revenue & occupancy over a date range. */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('reports.index', $this->build($from, $to) + [
            'from' => $from, 'to' => $to,
        ]);
    }

    /** Same daily figures as a CSV file for Excel. */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $data = $this->build($from, $to);
        $name = 'solkeb-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
            fputcsv($out, ['Date', 'Rooms sold', 'Rooms available', 'Occupancy %', 'Room revenue', 'Other revenue', 'Total revenue', 'Payments collected']);
            foreach ($data['days'] as $d) {
                fputcsv($out, [$d['date']->toDateString(), $d['sold'], $d['available'], $d['occupancy'],
                    number_format($d['room'], 2, '.', ''), number_format($d['other'], 2, '.', ''),
                    number_format($d['room'] + $d['other'], 2, '.', ''), number_format($d['collected'], 2, '.', '')]);
            }
            $t = $data['totals'];
            fputcsv($out, ['TOTAL', $t['sold'], $t['available'], $t['occupancy'], number_format($t['room'], 2, '.', ''),
                number_format($t['other'], 2, '.', ''), number_format($t['revenue'], 2, '.', ''), number_format($t['collected'], 2, '.', '')]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Night audit: one day's activity and the cashier reconciliation. */
    public function daily(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        if ($date->isFuture()) {
            $date = today();
        }

        $payments = Payment::with(['reservation.guest', 'receiver', 'account'])
            ->whereDate('paid_at', $date)->orderBy('paid_at')->get();

        $signed = fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;

        // Cash drawer check: what each staff member should hand over, per method
        $byCashier = $payments->groupBy(fn ($p) => $p->receiver?->name ?? 'Unknown')
            ->map(fn ($list) => $list->groupBy('method')->map(fn ($m) => $m->sum($signed)));

        $inHouse = Reservation::with(['guest', 'rooms'])
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereDate('check_in_date', '<=', $date)
            ->whereDate('check_out_date', '>', $date)
            ->whereNotNull('checked_in_at')
            ->get();

        $sellable = Room::where('status', '!=', 'out_of_order')->count();
        $roomsSold = $inHouse->sum(fn ($r) => $r->rooms->count());

        return view('reports.daily', [
            'date' => $date,
            'arrivals' => Reservation::with(['guest', 'rooms'])->whereDate('checked_in_at', $date)->get(),
            'departures' => Reservation::with(['guest', 'rooms'])->whereDate('checked_out_at', $date)->get(),
            'deleted' => Reservation::onlyTrashed()->with(['guest', 'deleter'])->whereDate('deleted_at', $date)->get(),
            'noShows' => Reservation::with('guest')->whereIn('status', ['no_show', 'cancelled'])->whereDate('updated_at', $date)->get(),
            'inHouse' => $inHouse,
            'payments' => $payments,
            'byCashier' => $byCashier,
            'byMethod' => $payments->groupBy('method')->map(fn ($m) => $m->sum($signed)),
            'byAccount' => $this->byAccount($payments),
            'collected' => $payments->sum($signed),
            'charges' => Charge::whereDate('charge_date', $date)->where('type', '!=', 'room')->sum('amount'),
            'roomsSold' => $roomsSold,
            'sellable' => $sellable,
            'openBalances' => Reservation::with('guest')->where('status', 'checked_in')->get()
                ->map(fn ($r) => ['r' => $r, 'balance' => $r->balance()])
                ->filter(fn ($x) => $x['balance'] > 0.009)->values(),
        ]);
    }

    /** Staff activity: who reserved, checked in, checked out and received money, for any period. */
    public function staff(Request $request)
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $activities = Activity::with(['user', 'reservation.guest'])
            ->whereBetween('created_at', [$start, $end])->latest('created_at')->latest('id')->limit(5000)->get();
        $payments = Payment::with('receiver')->whereBetween('paid_at', [$start, $end])->get();

        $methods = $payments->pluck('method')->unique()->sortBy(fn ($m) => array_search($m, array_keys(Payment::METHODS)))->values();
        $people = User::whereIn('id', $activities->pluck('user_id')->merge($payments->pluck('received_by'))->filter()->unique())
            ->orderBy('name')->get();

        $rows = $people->map(function ($user) use ($activities, $payments, $methods) {
            $mine = $activities->where('user_id', $user->id);
            $money = $payments->where('received_by', $user->id);
            $signed = fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;

            return (object) [
                'user' => $user,
                'reserved' => $mine->where('action', 'reserved')->count(),
                'checked_in' => $mine->where('action', 'checked_in')->count(),
                'checked_out' => $mine->where('action', 'checked_out')->count(),
                'payments' => $money->where('type', '!=', 'refund')->count(),
                'by_method' => $methods->mapWithKeys(fn ($m) => [$m => $money->where('method', $m)->sum($signed)]),
                'total' => $money->sum($signed),
            ];
        });

        return view('reports.staff', compact('from', 'to', 'rows', 'methods', 'activities'));
    }

    /**
     * My activity: a staff member's own work for any period (today by default), to check before handing over at the end of a shift.
     * Everyone sees only their own numbers; managers see everyone's in the Staff report.
     */
    public function mine(Request $request)
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $between = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $me = $request->user();

        $activities = Activity::with('reservation.guest')->where('user_id', $me->id)
            ->whereBetween('created_at', $between)->latest('created_at')->latest('id')->limit(2000)->get();
        $payments = Payment::with(['reservation.guest', 'account'])->where('received_by', $me->id)
            ->whereBetween('paid_at', $between)->orderBy('paid_at')->get();
        $signed = fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;

        return view('reports.mine', [
            'from' => $from,
            'to' => $to,
            'activities' => $activities,
            'payments' => $payments,
            'counts' => [
                'reserved' => $activities->where('action', 'reserved')->count(),
                'rooms' => DB::table('reservation_room')->whereIn('reservation_id', $activities->where('action', 'reserved')->pluck('reservation_id')->filter())->count(),
                'checked_in' => $activities->where('action', 'checked_in')->count(),
                'checked_out' => $activities->where('action', 'checked_out')->count(),
                'payments' => $payments->where('type', '!=', 'refund')->count(),
            ],
            'cash' => $payments->where('method', 'cash')->sum($signed),
            'byAccount' => $this->byAccount($payments),
            'total' => $payments->sum($signed),
        ]);
    }

    private function range(Request $request): array
    {
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from, $to];
    }

    private function build(Carbon $from, Carbon $to): array
    {
        $sellable = Room::where('status', '!=', 'out_of_order')->count();
        $lastNight = $to->copy()->min(today()); // nights after today have not happened yet

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $days[$d->toDateString()] = ['date' => $d->copy(), 'sold' => 0, 'available' => $sellable,
                'occupancy' => 0, 'room' => 0.0, 'other' => 0.0, 'collected' => 0.0];
        }

        // Room revenue is earned night by night, at the rate locked in the booking
        $stays = DB::table('reservation_room')
            ->join('reservations', 'reservations.id', '=', 'reservation_room.reservation_id')
            ->whereIn('reservations.status', ['checked_in', 'checked_out'])
            ->whereNull('reservations.deleted_at')
            ->whereDate('reservations.check_in_date', '<=', $lastNight)
            ->whereDate('reservations.check_out_date', '>', $from)
            ->get(['reservations.check_in_date', 'reservations.check_out_date', 'reservation_room.nightly_rate']);

        foreach ($stays as $s) {
            $night = Carbon::parse($s->check_in_date)->max($from)->copy();
            $end = Carbon::parse($s->check_out_date)->subDay()->min($lastNight)->copy();
            for (; $night->lte($end); $night->addDay()) {
                $key = $night->toDateString();
                $days[$key]['sold']++;
                $days[$key]['room'] += (float) $s->nightly_rate;
            }
        }

        // Everything else on bills (food, laundry, discounts...) counts on the day it was charged
        $other = Charge::where('charges.type', '!=', 'room')
            ->whereDate('charge_date', '>=', $from)
            ->whereDate('charge_date', '<=', $to)
            ->leftJoin('services', 'services.id', '=', 'charges.service_id')
            ->get(['charges.charge_date', 'charges.amount', 'charges.type', 'services.category']);

        $byCategory = ['Rooms' => 0.0];
        foreach ($other as $c) {
            $key = Carbon::parse($c->charge_date)->toDateString();
            $days[$key]['other'] += (float) $c->amount;
            $label = match (true) {
                $c->type === 'discount' => 'Discounts',
                $c->category !== null => ServiceController::CATEGORIES[$c->category] ?? ucfirst($c->category),
                default => 'Other charges',
            };
            $byCategory[$label] = ($byCategory[$label] ?? 0) + (float) $c->amount;
        }

        $payments = Payment::with('account')->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $byMethod = [];
        foreach ($payments as $p) {
            $amount = $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount;
            $days[$p->paid_at->toDateString()]['collected'] += $amount;
            $byMethod[$p->method] = ($byMethod[$p->method] ?? 0) + $amount;
        }
        arsort($byMethod);

        foreach ($days as &$d) {
            $d['occupancy'] = $d['available'] ? round($d['sold'] / $d['available'] * 100) : 0;
        }
        unset($d);

        $days = array_values($days);
        $sold = array_sum(array_column($days, 'sold'));
        $room = array_sum(array_column($days, 'room'));
        $available = $sellable * max(1, $from->diffInDays($lastNight->max($from)) + 1);
        $byCategory['Rooms'] = $room;
        $byCategory = array_filter($byCategory, fn ($v) => abs($v) > 0.009);
        uasort($byCategory, fn ($a, $b) => $b <=> $a);

        return [
            'days' => $days,
            'byMethod' => $byMethod,
            'byAccount' => $this->byAccount($payments),
            'byCategory' => $byCategory,
            'chart' => $this->chartBuckets($days),
            'totals' => [
                'sold' => $sold,
                'available' => $available,
                'occupancy' => $available ? round($sold / $available * 100) : 0,
                'room' => $room,
                'other' => array_sum(array_column($days, 'other')),
                'revenue' => $room + array_sum(array_column($days, 'other')),
                'collected' => array_sum(array_column($days, 'collected')),
                'adr' => $sold ? $room / $sold : 0,        // average room rate per night sold
                'revpar' => $available ? $room / $available : 0, // room revenue per available room night
            ],
            'owed' => Reservation::where('status', 'checked_in')->get()->sum(fn ($r) => max(0, $r->balance())),
        ];
    }

    /** Money in per bank account / wallet (and cash), after refunds, largest first. */
    private function byAccount($payments)
    {
        return $payments->groupBy(fn ($p) => $p->account ? $p->account->label() : (Payment::METHODS[$p->method] ?? $p->method))
            ->map(fn ($list) => $list->sum(fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount))
            ->sortDesc();
    }

    /** Daily bars for up to 2 months; monthly bars for longer ranges. */
    private function chartBuckets(array $days): array
    {
        if (count($days) <= 62) {
            return array_map(fn ($d) => ['label' => $d['date']->format('j M'), 'short' => $d['date']->format('j'),
                'value' => $d['room'] + $d['other']], $days);
        }
        $months = [];
        foreach ($days as $d) {
            $k = $d['date']->format('Y-m');
            $months[$k] ??= ['label' => $d['date']->format('F Y'), 'short' => $d['date']->format('M'), 'value' => 0];
            $months[$k]['value'] += $d['room'] + $d['other'];
        }

        return array_values($months);
    }
}
