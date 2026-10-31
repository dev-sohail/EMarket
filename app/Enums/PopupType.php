<?php

namespace App\Enums;

enum PopupType: string
{
    case Image = 'image';
    case SolidColor = 'solid_color';
    case CustomHtml = 'custom_html';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::SolidColor => 'Solid Color',
            self::CustomHtml => 'Custom HTML',
        };
    }
}
