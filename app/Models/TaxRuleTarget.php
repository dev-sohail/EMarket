<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRuleTarget extends PrefixedModel
{
    use HasFactory;

    protected $table = 'tax_rule_targets';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tax_rule_id',
        'target_type',
        'target_id',
    ];

    protected function casts(): array
    {
        return [
            'tax_rule_id' => 'string',
            'target_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'txt';
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }
}
