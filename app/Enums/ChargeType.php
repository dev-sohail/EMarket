<?php

namespace App\Enums;

enum ChargeType: string
{
    case Shipping = 'shipping';
    case CodFee = 'cod_fee';
    case Handling = 'handling';
    case CitySurcharge = 'city_surcharge';

    public function label(): string
    {
        return match ($this) {
            self::Shipping => 'Shipping',
            self::CodFee => 'COD Fee',
            self::Handling => 'Handling',
            self::CitySurcharge => 'City Surcharge',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Shipping => 'primary',
            self::CodFee => 'warning',
            self::Handling => 'info',
            self::CitySurcharge => 'danger',
        };
    }
}
