<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Hotel settings stored as key / value rows, cached. Use Setting::get('vat_percent', 15) and Setting::set(...).
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** Setting::get('hotel_name') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    /** Setting::set('vat_percent', 15) */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
