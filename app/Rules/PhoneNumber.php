<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number: an Ethiopian mobile (09… or 07…, 10 digits) or a foreign number in international form (+252…).
 * With $landline, Ethiopian landlines (011…, 025…) are accepted too (organisations, the hotel).
 *
 * Numbers are cleaned with PhoneNumber::normalize() before validating (requests do this in prepareForValidation),
 * so "0911 22 33 44", "+251 911 223344" and "251911223344" are all stored as "0911223344" and repeat guests match.
 */
class PhoneNumber implements ValidationRule
{
    public function __construct(private bool $landline = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $ethiopian = $this->landline ? '/^0[1-79]\d{8}$/' : '/^0[79]\d{8}$/';

        if (preg_match($ethiopian, $value) || preg_match('/^\+(?!251)[1-9]\d{7,14}$/', $value)) {
            return;
        }

        $fail($this->landline
            ? 'Enter a valid phone number: 10 digits starting with 0 (e.g. 0911223344 or 0257750000), or an international number starting with +.'
            : 'Enter a valid mobile number: 10 digits starting with 09 or 07 (e.g. 0911223344), or an international number starting with + (e.g. +252 63 4123456).');
    }

    /**
     * Tidy a typed number: drop spaces, dashes, dots and brackets; 00 → +; Ethiopian numbers in local form (09…).
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $p = preg_replace('/[\s\-.()\/]/', '', trim($phone));
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, '00')) {
            $p = '+'.substr($p, 2);
        }
        if (preg_match('/^\+?251(\d{9})$/', $p, $m)) {   // +251 911 223344 → 0911223344
            return '0'.$m[1];
        }
        if (preg_match('/^[79]\d{8}$/', $p)) {           // 911223344 (leading 0 forgotten) → 0911223344
            return '0'.$p;
        }

        return $p;
    }
}
