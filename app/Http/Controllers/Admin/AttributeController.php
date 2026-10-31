<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\Catalog\AttributeService;

class AttributeController extends Controller
{
    protected AttributeService $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function index()
    {
        // Ordered by the sort_order the form exposes, so the order the operator
        // chose drives the list. The storefront filter panel uses the same order,
        // which makes this the merchandising order rather than a by-product one.
        $query = Attribute::with('values')
            ->withCount('values');

        // Search by name
        if (request()->filled('search')) {
            $query->where('name', 'like', '%'.request()->search.'%');
        }

        // Filter by type
        if (request()->filled('type')) {
            $query->where('type', request()->type);
        }

        // Filter by filterable
        if (request()->filled('filterable')) {
            $query->where('is_filterable', request()->filterable);
        }

        $attributes = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Deleting an attribute cascades to its values and every product link
        // that pointed at them. The confirmation names the number of products
        // affected so the count is visible before the click, not after.
        // Both tables carry an attribute_id, so every reference is qualified.
        $productsInUse = AttributeValue::query()
            ->select('attribute_values.attribute_id')
            ->selectRaw('COUNT(DISTINCT product_attribute_values.product_id) as products_count')
            ->join(
                'product_attribute_values',
                'product_attribute_values.attribute_value_id',
                '=',
                'attribute_values._id'
            )
            ->groupBy('attribute_values.attribute_id')
            ->pluck('products_count', 'attribute_id');

        return view('admin.attributes.index', compact('attributes', 'productsInUse'));
    }

    public function create()
    {
        return view('admin.attributes.create');
    }

    public function store(StoreAttributeRequest $request)
    {
        $this->attributeService->create($request->validated());

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Attribute created successfully.');
    }

    public function edit(Attribute $attribute)
    {
        $attribute->load('values');

        return view('admin.attributes.edit', compact('attribute'));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute)
    {
        $this->attributeService->update($attribute->getKey(), $request->validated());

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Attribute updated successfully.');
    }

    public function destroy(Attribute $attribute)
    {
        $this->attributeService->delete($attribute->getKey());

        return redirect()->route('admin.attributes.index')
            ->with('success', 'Attribute deleted successfully.');
    }
}
