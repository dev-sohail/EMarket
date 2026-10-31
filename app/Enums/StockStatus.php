<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case OnBackorder = 'on_backorder';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::OutOfStock => 'Out of Stock',
            self::OnBackorder => 'On Backorder',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::OutOfStock => 'danger',
            self::OnBackorder => 'warning',
        };
    }
}
