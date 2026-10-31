<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Attribute extends PrefixedModel
{
    use HasFactory;

    protected $table = 'attributes';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_filterable',
        'is_variant',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_variant' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'att';
    }

    /**
     * Remove the attribute's values before the attribute row disappears.
     *
     * `attribute_values` has no foreign key and no cascade, so without this the
     * values outlive the attribute they belong to. The service does this too,
     * but only for callers that route through it; a test, a seeder or a console
     * command calling `delete()` directly would otherwise leave orphans, which
     * is why the cleanup lives on the model that owns the relationship.
     *
     * Clearing by `attribute_id` first also collects pivot rows whose value has
     * already gone missing, so an attribute cannot inherit orphans left by an
     * earlier bug.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $attribute): void {
            foreach (AttributeValue::ATTRIBUTE_VALUE_PIVOTS as $pivot) {
                DB::table($pivot)
                    ->where('attribute_id', $attribute->getKey())
                    ->delete();
            }

            // Each value is deleted individually rather than through
            // `$attribute->values()->delete()`: a mass delete on the relation
            // bypasses Eloquent events, which would skip the value's own
            // pivot cleanup.
            foreach ($attribute->values()->get() as $value) {
                $value->delete();
            }
        });
    }

    public function values(): HasMany
    {
        // Ordered so the order the operator chose in the admin form is the order
        // every reader sees: the index, the product form and the storefront
        // filter all render $attribute->values.
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('_id');
    }
}
