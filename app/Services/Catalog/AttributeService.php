<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    public function __construct(
        protected Attribute $attribute
    ) {}

    public function getAll(): Collection
    {
        return Attribute::with('values')->get();
    }

    public function create(array $data): Attribute
    {
        return DB::transaction(function () use ($data) {
            $values = $data['values'] ?? [];
            unset($data['values']);
            $attribute = Attribute::create($data);

            foreach (array_values($values) as $position => $value) {
                $attribute->values()->create([
                    'value' => $value,
                    'sort_order' => $position,
                ]);
            }

            return $attribute->load('values');
        });
    }

    public function update(string $id, array $data): Attribute
    {
        return DB::transaction(function () use ($id, $data) {
            $attribute = Attribute::findOrFail($id);
            $values = $data['values'] ?? [];
            unset($data['values']);
            $attribute->update($data);

            if ($this->hasSubmittedValues($values)) {
                $this->syncValues($attribute, $values);
            }

            return $attribute->load('values');
        });
    }

    /**
     * Whether the submission actually carries values to reconcile.
     *
     * The form always renders at least one row, so a request that only ever
     * contains blanks arrives as `['']` or `['', '']` — non-empty as an array,
     * but with nothing in it. Feeding that to `syncValues` reads as "remove
     * every value", which silently deletes each one together with the
     * `product_attribute_values` and `variant_attribute_values` rows pointing at
     * them. Blanks alone are therefore treated as "the list was not touched".
     *
     * Removing every value stays possible in principle, but the store requests
     * require at least one value for the types that draw from a list, so it is
     * not a state the UI can ask for.
     *
     * @param  array<int, mixed>  $values
     */
    private function hasSubmittedValues(array $values): bool
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function delete(string $id): bool
    {
        $attribute = Attribute::findOrFail($id);

        // The pivot cleanup belongs to the models, not here: `deleting` hooks on
        // Attribute and AttributeValue clear the pivots for every delete path,
        // and the transaction still covers them because they fire inside it.
        return DB::transaction(fn () => $attribute->delete());
    }

    /**
     * Reconcile the value list by its text instead of replacing the whole set:
     * a value whose text is unchanged keeps its `_id`, so every
     * product_attribute_values and variant_attribute_values row that points at
     * it stays valid.
     *
     * The submitted order is authoritative: kept values are renumbered to match
     * it and new values are inserted at their position, so reordering the form
     * actually reorders the attribute.
     *
     * @param  array<int, mixed>  $values
     */
    protected function syncValues(Attribute $attribute, array $values): void
    {
        $submitted = collect($values)
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
            ->filter(fn (?string $value) => $value !== null && $value !== '')
            ->unique()
            ->values();

        $kept = [];
        $removedValueIds = [];
        $surviving = [];

        foreach ($attribute->values()->get() as $existing) {
            $value = trim((string) $existing->value);

            if (! array_key_exists($value, $kept) && $submitted->containsStrict($value)) {
                $kept[$value] = true;
                $surviving[$value] = $existing;

                continue;
            }

            $removedValueIds[] = (string) $existing->getKey();
        }

        $this->deleteValuesWithPivots($removedValueIds);

        foreach ($submitted as $position => $value) {
            if (array_key_exists($value, $surviving)) {
                $surviving[$value]->update(['sort_order' => $position]);

                continue;
            }

            $attribute->values()->create([
                'value' => $value,
                'sort_order' => $position,
            ]);
        }
    }

    /**
     * Delete values one at a time so each one's own `deleting` hook clears its
     * pivot rows. A mass `whereIn()->delete()` would take the same rows in one
     * statement but skip the events, which is exactly the orphan this guards
     * against.
     *
     * @param  array<int, string>  $valueIds
     */
    protected function deleteValuesWithPivots(array $valueIds): void
    {
        $valueIds = array_values(array_unique(array_filter($valueIds)));

        if ($valueIds === []) {
            return;
        }

        AttributeValue::whereIn('_id', $valueIds)
            ->get()
            ->each(fn (AttributeValue $value) => $value->delete());
    }
}
