<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnStatusHistory extends PrefixedModel
{
    use HasFactory;

    protected $table = 'return_status_histories';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'return_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'return_id' => 'string',
            'changed_by' => 'string',
            'from_status' => ReturnStatus::class,
            'to_status' => ReturnStatus::class,
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'rsh';
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }
}
