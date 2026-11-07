<?php

namespace App\Support;

final class Money
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'INR', 'PKR', 'AED', 'SAR', 'CAD', 'AUD', 'JPY'];

    public static function format(int $cents, string $currency = 'USD'): string
    {
        return $currency.' '.number_format($cents / 100, 2);
    }

    /** "12.5" => 1250; empty => null. */
    public static function toCents(string|int|float|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (int) round((float) $amount * 100);
    }

    public static function fromCents(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }
}
