<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::ReadyToShip => 'Ready to Ship',
            self::Shipped => 'Shipped',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Processing => 'primary',
            self::ReadyToShip => 'info',
            self::Shipped => 'primary',
            self::OutForDelivery => 'info',
            self::Delivered => 'success',
            self::Completed => 'success',
            self::Returned => 'secondary',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Statuses whose orders must not count towards revenue or sales figures.
     *
     * OrderService::getSummaryStats() excludes exactly these from its revenue
     * card; every other money figure in the app (analytics dashboards, exports,
     * per-date helpers) has to agree with it or the same period shows two
     * different totals.
     *
     * @return list<string>
     */
    public static function nonRevenue(): array
    {
        return [self::Cancelled->value, self::Returned->value];
    }
}
