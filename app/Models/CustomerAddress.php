<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends PrefixedModel
{
    use HasFactory;

    protected $table = 'customer_addresses';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'type',
        'is_default',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
            'is_default' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'cad';
    }

    /**
     * The recipient name as one string.
     *
     * `customer_addresses` stores `first_name`/`last_name` and has no
     * `full_name` column, so the address list used to render `$address->full_name`
     * — an attribute the table does not have — and printed a blank line where the
     * recipient's name should be. The accessor mirrors `Customer::full_name`.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
