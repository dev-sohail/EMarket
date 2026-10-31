<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpointPermission extends PrefixedModel
{
    use HasFactory, PrefixedId;

    protected $table = 'api_endpoint_permissions';

    protected $fillable = [
        'api_endpoint_id',
        'permission_id',
        'access_type',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'aeperm';
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id', '_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id', '_id');
    }

    /**
     * Evaluate the ABAC conditions against a subject (the authenticated user).
     *
     * Conditions are stored as an array of rules, each of the form
     * `{"attribute": "value"}` or `{"attribute": {"operator": "value"}}`.
     * Unknown attributes short-circuit to false, so an endpoint that lists a
     * condition is opt-in rather than opt-out.
     */
    public function evaluateConditions(array $subject): bool
    {
        $conditions = $this->conditions;

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $subject)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateCondition(array $condition, array $subject): bool
    {
        foreach ($condition as $attribute => $expected) {
            $actual = $subject[$attribute] ?? null;

            if (is_array($expected) && count($expected) === 1) {
                $operator = array_key_first($expected);
                $value = $expected[$operator];

                if (! $this->compare($actual, $operator, $value)) {
                    return false;
                }
            } elseif ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    protected function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq', '=' => $actual === $expected,
            'ne', '!=' => $actual !== $expected,
            'gt' => $actual > $expected,
            'gte', '>=' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte', '<=' => $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_array($actual) && in_array($expected, $actual, true),
            'regex' => is_string($actual) && is_string($expected) && preg_match($expected, $actual) === 1,
            default => $actual === $expected,
        };
    }
}
