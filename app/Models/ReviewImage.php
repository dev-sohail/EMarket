<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewImage extends PrefixedModel
{
    use HasFactory;

    protected $table = 'review_images';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'review_id',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'review_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'rvi';
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
