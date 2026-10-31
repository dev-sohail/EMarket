<?php

namespace App\Services\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use Illuminate\Database\Eloquent\Collection;

class ShippingService
{
    /**
     * Quote every active shipping method for a destination.
     *
     * Zone resolution is explicit and never silently free:
     *
     *  1. the city as listed in `shipping_zone_cities` (matched on a normalised
     *     string, ties broken by `_id`);
     *  2. otherwise the default zone — `config('shipping.default_zone')`, which
     *     may be a zone `_id` or a zone name, else the first active zone that has
     *     no cities at all and therefore acts as the catch-all;
     *  3. otherwise the destination is reported unavailable, and the callers
     *     (CheckoutService) surface that instead of charging nothing.
     *
     * Rule precedence is deterministic: the most specific matching rule wins,
     * see self::comparePrecedence().
     *
     * @return array{available: bool, zone: string|null, methods: array<int, array{method_id: string, name: string, cost: float, estimated_days: int|null}>, message?: string}
     */
    public function calculate(
        string $city,
        float $weight,
        float $orderValue,
        int $quantity
    ): array {
        $city = $this->normaliseCity($city);

        if ($city === '') {
            return $this->unavailable('A destination city is required to calculate shipping.');
        }

        $zone = $this->resolveZone($city);

        if (! $zone) {
            return $this->unavailable("Shipping is not configured for '{$city}'.");
        }

        $methods = ShippingMethod::where('is_active', true)
            ->with(['rules' => function ($query) use ($zone) {
                $query->where('shipping_zone_id', $zone->getKey())
                    ->where('is_active', true)
                    ->orderBy('_id');
            }])
            ->orderBy('sort_order')
            ->orderBy('_id')
            ->get();

        $availableMethods = [];

        foreach ($methods as $method) {
            $rate = $this->rateForMethod($method, $weight, $orderValue, $quantity);

            if ($rate === null) {
                continue;
            }

            $availableMethods[] = [
                'method_id' => $method->getKey(),
                'name' => $method->name,
                'cost' => round((float) $rate, 2),
                'estimated_days' => $method->estimated_days,
            ];
        }

        if ($availableMethods === []) {
            return $this->unavailable("No shipping methods are available for '{$city}'.");
        }

        return [
            'available' => true,
            'zone' => $zone->name,
            'methods' => $availableMethods,
        ];
    }

    public function getZones(): Collection
    {
        return ShippingZone::with(['cities', 'rules.method'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('_id')
            ->get();
    }

    public function getMethods(): Collection
    {
        return ShippingMethod::with('rules.shippingZone')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('_id')
            ->get();
    }

    /**
     * The zone a city ships under, or the default zone when it is not listed.
     */
    private function resolveZone(string $city): ?ShippingZone
    {
        $zone = ShippingZoneCity::where('city', $city)
            ->whereHas('shippingZone', fn ($query) => $query->where('is_active', true))
            ->orderBy('_id')
            ->first()?->shippingZone;

        return $zone ?? $this->defaultZone();
    }

    /**
     * The fallback for a city that is on no zone's city list.
     *
     * `config('shipping.default_zone')` (a zone `_id` or a zone name) always
     * wins. Failing that, an active zone with no cities at all is used, which
     * gives the existing zone records a catch-all meaning. Returns null when
     * neither exists so the caller can report the destination as unavailable
     * rather than defaulting to free shipping.
     */
    private function defaultZone(): ?ShippingZone
    {
        $default = config('shipping.default_zone');

        if (filled($default)) {
            $configured = ShippingZone::where('is_active', true)
                ->where(fn ($query) => $query->where('_id', $default)->orWhere('name', $default))
                ->orderBy('sort_order')
                ->orderBy('_id')
                ->first();

            if ($configured) {
                return $configured;
            }
        }

        return ShippingZone::where('is_active', true)
            ->whereDoesntHave('cities')
            ->orderBy('sort_order')
            ->orderBy('_id')
            ->first();
    }

    /**
     * The rate for a method: the first matching rule in precedence order that
     * yields a rate. When the zone has no active rules for this method at all,
     * the method's own base + per-kg cost applies. When rules exist but none
     * match the order, the method genuinely does not apply and is not offered.
     */
    private function rateForMethod(
        ShippingMethod $method,
        float $weight,
        float $orderValue,
        int $quantity
    ): ?float {
        $rules = $method->rules
            ->filter(fn (ShippingRule $rule) => $rule->matches($weight, $orderValue, $quantity))
            ->sort(fn (ShippingRule $a, ShippingRule $b) => $this->comparePrecedence($a, $b))
            ->values();

        foreach ($rules as $rule) {
            $rate = $rule->calculateRate($weight, $orderValue, $quantity);

            if ($rate !== null) {
                return $rate;
            }
        }

        if ($method->rules->isEmpty()) {
            return (float) $method->base_cost + ($weight * (float) ($method->per_kg_cost ?? 0));
        }

        return null;
    }

    /**
     * Total, deterministic ordering of the rules of one method — the most
     * specific match wins:
     *
     *  1. more bands constrained (weight / order value / quantity) beats fewer;
     *  2. a narrower band beats a wider one, an unset bound counting as unbounded;
     *  3. `_id` ascending is the final tie-breaker, so no two rules ever compare
     *     equal and the same cart always quotes the same price.
     */
    private function comparePrecedence(ShippingRule $a, ShippingRule $b): int
    {
        return [-$this->constraintCount($a), $this->bandWidth($a), (string) $a->getKey()]
            <=> [-$this->constraintCount($b), $this->bandWidth($b), (string) $b->getKey()];
    }

    /**
     * How many of the six weight/value/quantity bounds the rule constrains.
     */
    private function constraintCount(ShippingRule $rule): int
    {
        $bounds = [
            'min_weight',
            'max_weight',
            'min_order_value',
            'max_order_value',
            'min_quantity',
            'max_quantity',
        ];

        return count(array_filter(
            $bounds,
            fn (string $bound) => ! is_null($rule->getAttribute($bound))
        ));
    }

    /**
     * The combined width of the three bands. A band with an unset bound counts
     * as unbounded, so a rule is never treated as narrower than it is.
     */
    private function bandWidth(ShippingRule $rule): float
    {
        $width = 0.0;

        $bands = [
            ['min_weight', 'max_weight'],
            ['min_order_value', 'max_order_value'],
            ['min_quantity', 'max_quantity'],
        ];

        foreach ($bands as [$min, $max]) {
            $lower = $rule->getAttribute($min);
            $upper = $rule->getAttribute($max);

            $width += (is_null($lower) || is_null($upper))
                ? PHP_FLOAT_MAX
                : (float) $upper - (float) $lower;
        }

        return $width;
    }

    /**
     * `shipping_zone_cities.city` is a `char(255)` with a case-insensitive
     * collation, so the comparison is an exact match once padding and repeated
     * whitespace are removed.
     */
    private function normaliseCity(string $city): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $city));
    }

    /**
     * @return array{available: false, zone: null, methods: array, message: string}
     */
    private function unavailable(string $message): array
    {
        return [
            'available' => false,
            'zone' => null,
            'methods' => [],
            'message' => $message,
        ];
    }
}
