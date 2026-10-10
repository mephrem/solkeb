<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Hotel settings (admin): name and address on printed bills, VAT, check-in / out times, booking code prefix, deposits on/off.
 */
class SettingController extends Controller
{
    /** Settings the hotel can change, with their validation rules. */
    public const FIELDS = [
        'hotel_name' => ['required', 'string', 'max:100'],
        'tagline' => ['nullable', 'string', 'max:100'],
        'city' => ['required', 'string', 'max:100'],
        'region' => ['nullable', 'string', 'max:150'],
        'address' => ['nullable', 'string', 'max:200'],
        'phone' => ['nullable', 'string', 'max:50'],
        'email' => ['nullable', 'email', 'max:150'],
        'tin' => ['nullable', 'string', 'max:30'],
        'vat_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        'check_in_time' => ['required', 'date_format:H:i'],
        'check_out_time' => ['required', 'date_format:H:i'],
        'half_day_until' => ['nullable', 'date_format:H:i'],               // late check-out ends (default 14:30)
        'half_day_percent' => ['nullable', 'numeric', 'min:0', 'max:100'], // share of the night charged (default 50)
        'booking_prefix' => ['required', 'alpha_num', 'max:6'],
        'invoice_footer' => ['nullable', 'string', 'max:300'],
        'take_deposits' => ['nullable', 'boolean'], // off: guests pay during the stay or at check-out, nothing at booking
    ];

    public function edit()
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('settings.edit', compact('values'));
    }

    public function update(Request $request)
    {
        $data = $request->validate(self::FIELDS);

        $data['take_deposits'] = $request->boolean('take_deposits') ? '1' : '0'; // unticked boxes are not sent
        foreach ($data as $key => $value) {
            Setting::set($key, $key === 'booking_prefix' ? strtoupper($value) : $value);
        }

        return back()->with('success', 'Settings saved.');
    }
}
