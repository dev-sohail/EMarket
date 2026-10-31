<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

abstract class PrefixedModel extends Model
{
    use PrefixedId;

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public function hasMany($related, $foreignKey = null, $localKey = null, $relation = null): HasMany
    {
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this)).'_id';
        }

        if (is_null($localKey)) {
            $localKey = $this->primaryKey;
        }

        return parent::hasMany($related, $foreignKey, $localKey, $relation);
    }

    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null): BelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        // The foreign key lives on the child table, so it is named after the
        // relation being declared: CartItem::product() -> cart_items.product_id.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_id';
        }

        if (is_null($ownerKey)) {
            $ownerKey = $this->primaryKey;
        }

        return parent::belongsTo($related, $foreignKey, $ownerKey, $relation);
    }

    public function hasOne($related, $foreignKey = null, $localKey = null, $relation = null): HasOne
    {
        // The foreign key lives on the child table, but is named after this
        // (declaring) class: Order::shippingAddress() -> order_addresses.order_id.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake(class_basename($this)).'_id';
        }

        if (is_null($localKey)) {
            $localKey = $this->primaryKey;
        }

        return parent::hasOne($related, $foreignKey, $localKey, $relation);
    }

    public function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null): BelongsToMany
    {
        if (is_null($parentKey)) {
            $parentKey = $this->primaryKey;
        }

        if (is_null($relatedKey)) {
            $relatedKey = (new $related)->getKeyName();
        }

        if (is_null($foreignPivotKey)) {
            $foreignPivotKey = Str::snake(class_basename($this)).'_id';
        }

        if (is_null($relatedPivotKey)) {
            $relatedPivotKey = Str::snake(class_basename($related)).'_id';
        }

        return parent::belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation);
    }
}
