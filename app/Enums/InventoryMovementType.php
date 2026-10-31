<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case StockIn = 'stock_in';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case Correction = 'correction';
    case Reserved = 'reserved';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::Sale => 'Sale',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
            self::Damage => 'Damage',
            self::Correction => 'Correction',
            self::Reserved => 'Reserved',
            self::Released => 'Released',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::StockIn => 'success',
            self::Sale => 'primary',
            self::Return => 'warning',
            self::Adjustment => 'info',
            self::Damage => 'danger',
            self::Correction => 'secondary',
            self::Reserved => 'dark',
            self::Released => 'light',
        };
    }
}
