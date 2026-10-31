<?php

namespace App\Enums;

enum PopupSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Medium => 'Medium',
            self::Large => 'Large',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Small => 'max-w-md',
            self::Medium => 'max-w-2xl',
            self::Large => 'max-w-5xl',
        };
    }
}
