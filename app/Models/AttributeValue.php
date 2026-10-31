<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AttributeValue extends PrefixedModel
{
    use HasFactory;

    /**
     * The pivots that reference an attribute value.
     *
     * Neither declares a foreign key, so a removed value has to have its rows
     * cleared explicitly or they stay behind as orphans. `Attribute` uses the
     * same list when it deletes its values in bulk, so the two paths cannot
     * disagree about which tables matter.
     *
     * @var array<int, string>
     */
    public const ATTRIBUTE_VALUE_PIVOTS = [
        'product_attribute_values',
        'variant_attribute_values',
    ];

    protected $table = 'attribute_values';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'attribute_id',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'string',
            'sort_order' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'atv';
    }

    /**
     * Detach from products and variants before the value row disappears.
     *
     * Without this, a removed value keeps pointing at products through pivot
     * rows that nothing will ever clean up. This runs for every delete path —
     * the admin controller, the bulk reconcile in AttributeService, a test or a
     * console command — rather than only the one that goes through the service.
     *
     * Tagged `deleting` so the transaction the caller opened still covers it.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $value): void {
            foreach (self::ATTRIBUTE_VALUE_PIVOTS as $pivot) {
                DB::table($pivot)
                    ->where('attribute_value_id', $value->getKey())
                    ->delete();
            }
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
