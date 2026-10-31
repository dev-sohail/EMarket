<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case InWarehouse = 'in_warehouse';
    case Picked = 'picked';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InWarehouse => 'In Warehouse',
            self::Picked => 'Picked',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::InWarehouse => 'info',
            self::Picked => 'info',
            self::Packed => 'primary',
            self::Shipped => 'success',
            self::Delivered => 'success',
        };
    }
}
