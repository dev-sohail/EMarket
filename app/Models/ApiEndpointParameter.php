<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpointParameter extends PrefixedModel
{
    use HasFactory, PrefixedId;

    protected $table = 'api_endpoint_parameters';

    protected $fillable = [
        'api_endpoint_id',
        'name',
        'location',
        'type',
        'required',
        'default_value',
        'description',
        'rules',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'rules' => 'array',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'aeparam';
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id', '_id');
    }

    /**
     * Cast the stored JSON rules into Laravel validation rules.
     *
     * Rules may be stored either as a JSON object (`{"min": 3}`) or as a
     * pipe-separated string (`required|min:3`). Both are normalised to the
     * array form that Validator::make expects.
     */
    public function validationRules(): array
    {
        $rules = $this->rules;

        if (is_string($rules)) {
            $decoded = json_decode($rules, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return array_filter(explode('|', $rules));
        }

        return is_array($rules) ? $rules : [];
    }
}
