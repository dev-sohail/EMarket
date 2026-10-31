<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Returned => 'Returned',
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::UnderReview => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Returned => 'primary',
            self::Completed => 'success',
        };
    }
}
