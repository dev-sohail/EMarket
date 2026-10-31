<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain pivot model for `product_attribute_values`.
 *
 * The table has no surrogate key of its own: the (product_id, attribute_value_id)
 * pair is the row's identity, exactly like product_brands, product_tags and
 * coupon_products. It therefore does not extend PrefixedModel, whose PrefixedId
 * `creating` hook would try to write an `_id` value that this table has no
 * column for.
 */
class ProductAttributeValue extends Model
{
    use HasFactory;

    protected $table = 'product_attribute_values';

    /**
     * The pivot is keyed by the (product_id, attribute_value_id) pair, not by
     * a single column.
     *
     * @var string|null
     */
    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'attribute_value_id',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'string',
            'attribute_id' => 'string',
            'attribute_value_id' => 'string',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', '_id');
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id', '_id');
    }
}
