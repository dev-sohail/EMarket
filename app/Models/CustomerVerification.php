<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerVerification extends PrefixedModel
{
    use HasFactory;

    protected $table = 'customer_verifications';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'type',
        'code',
        'verified_at',
        'expires_at',
    ];

    protected $hidden = [
        'code',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'cve';
    }
}
