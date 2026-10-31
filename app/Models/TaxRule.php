<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRule extends PrefixedModel
{
    use HasFactory;

    protected $table = 'tax_rules';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'rate',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'txr';
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TaxRuleTarget::class);
    }
}
