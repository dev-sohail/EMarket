<?php

namespace App\Enums;

enum BannerPosition: string
{
    case Homepage = 'homepage';
    case Promotional = 'promotional';
    case Footer = 'footer';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Homepage => 'Homepage',
            self::Promotional => 'Promotional',
            self::Footer => 'Footer',
            self::Sale => 'Sale',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Homepage => 'primary',
            self::Promotional => 'success',
            self::Footer => 'secondary',
            self::Sale => 'danger',
        };
    }
}
