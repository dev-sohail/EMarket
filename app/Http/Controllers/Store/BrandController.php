<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;

class BrandController extends Controller
{
    public function show(Brand $brand)
    {
        abort_unless($brand->is_active, 404);

        $products = Product::with(['images', 'variants', 'categories'])
            ->published()
            ->whereHas('brands', fn ($q) => $q->where('brands._id', $brand->getKey()))
            ->latest()
            ->paginate(15);

        return view('store.brand.show', compact('brand', 'products'));
    }
}
