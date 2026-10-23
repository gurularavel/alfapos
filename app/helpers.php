<?php

use App\Models\SiteSetting;
use App\Support\Tenancy;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** Məbləği tenant valyutası ilə formatlayır: 1234.5 → "1 234.50 ₼". */
    function money(float|int|string|null $amount): string
    {
        $currency = app(Tenancy::class)->tenant()?->currency ?? '₼';

        return number_format((float) $amount, 2, '.', ' ').' '.$currency;
    }
}

if (! function_exists('unit_money')) {
    /** Vahid qiyməti (qram/ml qiyməti çox kiçik ola bilər): 2–4 onluq rəqəm, 0.0145 → "0.0145 ₼", 12.5 → "12.50 ₼". */
    function unit_money(float|int|string|null $amount): string
    {
        $currency = app(Tenancy::class)->tenant()?->currency ?? '₼';
        $formatted = rtrim(number_format((float) $amount, 4, '.', ' '), '0');

        return (strlen(substr(strrchr($formatted, '.'), 1)) < 2 ? number_format((float) $amount, 2, '.', ' ') : $formatted).' '.$currency;
    }
}

if (! function_exists('site')) {
    /** Saytın əlaqə/rekvizit ayarları (superadmin → Sayt), telefonların beynəlxalq formaları ilə. */
    function site(?string $key = null): mixed
    {
        static $values = null;
        if ($values === null || app()->runningUnitTests()) {
            $values = SiteSetting::values();
            foreach (['phone', 'city_phone', 'whatsapp'] as $field) {
                $values[$field.'_intl'] = SiteSetting::intl($values[$field]);
            }
        }

        return $key ? ($values[$key] ?? null) : $values;
    }
}

if (! function_exists('qty')) {
    /** 12.5000 → "12.5" */
    function qty(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}

if (! function_exists('query_date')) {
    /**
     * Filtr tarixi (?from=, ?to=): səhv və ya massiv dəyər gəlsə 500 əvəzinə defolt tarix götürülür.
     */
    function query_date(mixed $value, string $default): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (InvalidFormatException) {
            }
        }

        return Carbon::parse($default);
    }
}

if (! function_exists('search_key')) {
    /**
     * Axtarış açarı: "Çay", "cay", "chay" eyni olsun (resources/js/search.js ilə eyni qayda).
     * $schwa: "ə" hərfinin yazılışı — "e" (defolt) və ya "a".
     */
    function search_key(?string $value, string $schwa = 'e'): string
    {
        $value = mb_strtolower(str_replace('İ', 'i', (string) $value));
        $value = strtr($value, ['ç' => 'c', 'ş' => 's', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ü' => 'u', 'ə' => $schwa]);

        return trim(str_replace(['ch', 'sh', 'gh', 'kh', 'zh'], ['c', 's', 'g', 'x', 'j'], $value));
    }
}

if (! function_exists('search_index')) {
    /** Bazada saxlanılan axtarış mətni: "ə"-nin hər iki yazılışı ("xemir | xamir"), sorğu search_key() ilə LIKE axtarılır. */
    function search_index(?string $value): string
    {
        return implode(' | ', array_unique([search_key($value), search_key($value, 'a')]));
    }
}
