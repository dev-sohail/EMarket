<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends PrefixedModel
{
    use HasFactory;

    protected $table = 'tags';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected function getIdPrefix(): string
    {
        return 'tag';
    }

    /**
     * Detach from products before the tag row disappears.
     *
     * `product_tags` has no foreign key and no cascade, so without this the
     * pivot rows outlive the tag. That is not merely untidy: the dangling rows
     * make a product report a tag that no longer exists, and they are what
     * makes the tag reappear, attached to the old products, the moment an id is
     * reused. A pivot table is owned by the model that deletes the record, so
     * the cleanup belongs here rather than in the controller — seeder, console
     * and test all get it for free.
     *
     * Tagged `deleting` rather than `deleted` so the relation is still loaded.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $tag): void {
            $tag->products()->detach();
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tags');
    }
}
