<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends PrefixedModel
{
    use HasFactory;

    protected $table = 'order_status_histories';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
            'changed_by' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'osh';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
