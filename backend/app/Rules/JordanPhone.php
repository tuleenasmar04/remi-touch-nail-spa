<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class JordanPhone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\+962(?:7[4-9]\d{7}|[2356]\d{7})$/', self::normalize((string) $value))) {
            $fail(app()->getLocale() === 'ar' ? 'يرجى إدخال رقم هاتف أردني صالح.' : 'Enter a valid Jordanian phone number.');
        }
    }

    public static function normalize(string $value): string
    {
        $value = strtr($value, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $value = preg_replace('/[\s()\-\.]/u', '', $value);
        if (str_starts_with($value, '00962')) {
            return '+962'.substr($value, 5);
        }
        if (str_starts_with($value, '962')) {
            return '+'.$value;
        }
        if (str_starts_with($value, '0')) {
            return '+962'.substr($value, 1);
        }
        if (preg_match('/^(7[4-9]\d{7}|[2356]\d{7})$/', $value)) {
            return '+962'.$value;
        }

        return $value;
    }
}
