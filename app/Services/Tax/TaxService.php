<?php

namespace App\Services\Tax;

use App\Models\TaxRule;
use Illuminate\Database\Eloquent\Collection;

class TaxService
{
    public function __construct(
        protected TaxRule $taxRule
    ) {}

    public function calculate(float $subtotal, array $targets = []): array
    {
        $query = $this->taxRule->where('is_active', true);

        if (! empty($targets)) {
            $query->whereHas('targets', function ($q) use ($targets) {
                $q->whereIn('target_type', $targets);
            });
        }

        $rules = $query->get();
        $totalTax = 0;
        $taxBreakdown = [];

        foreach ($rules as $rule) {
            $taxAmount = $rule->type === 'fixed'
                ? $rule->rate
                : $subtotal * ($rule->rate / 100);
            $totalTax += $taxAmount;

            $taxBreakdown[] = [
                'id' => $rule->getKey(),
                'name' => $rule->name,
                'rate' => $rule->rate,
                'type' => $rule->type,
                'amount' => round($taxAmount, 2),
            ];
        }

        return [
            'total_tax' => round($totalTax, 2),
            'breakdown' => $taxBreakdown,
        ];
    }

    public function getRules(): Collection
    {
        return $this->taxRule->with('targets')->get();
    }
}
