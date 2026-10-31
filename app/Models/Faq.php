<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faq extends PrefixedModel
{
    use HasFactory;

    protected $table = 'faqs';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'faq';
    }
}
