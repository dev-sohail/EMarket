<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed Amount',
            self::FreeShipping => 'Free Shipping',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'primary',
            self::Fixed => 'success',
            self::FreeShipping => 'info',
        };
    }
}
