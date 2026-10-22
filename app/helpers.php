<?php

use App\Support\Tenancy;

if (! function_exists('money')) {
    /** Məbləği tenant valyutası ilə formatlayır: 1234.5 → "1 234.50 ₼". */
    function money(float|int|string|null $amount): string
    {
        $currency = app(Tenancy::class)->tenant()?->currency ?? '₼';

        return number_format((float) $amount, 2, '.', ' ').' '.$currency;
    }
}

if (! function_exists('qty')) {
    /** 12.5000 → "12.5" */
    function qty(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
