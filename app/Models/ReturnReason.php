<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnReason extends PrefixedModel
{
    use HasFactory;

    protected $table = 'return_reasons';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'reason',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'rrs';
    }
}
