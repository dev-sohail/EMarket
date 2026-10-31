<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enquiry extends PrefixedModel
{
    use HasFactory;

    protected $table = 'enquiries';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'admin_notes',
        'read_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'user_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'enq';
    }
}
