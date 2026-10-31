<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;

class CategoryController extends Controller
{
    public function show(Category $category)
    {
        abort_unless($category->is_active, 404);

        $category->load(['children', 'parent']);

        $query = Product::with(['images', 'variants', 'categories', 'flashSales', 'campaigns',
            'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->published()
            ->whereHas('categories', fn ($q) => $q->where('categories._id', $category->getKey()));

        $sort = request('sort', 'default');
        match ($sort) {
            'newest' => $query->latest(),
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            default => $query->latest(),
        };

        $products = $query->paginate(15)->withQueryString();

        $subcategories = $category->children()->active()->orderBy('sort_order')->get();

        return view('store.category.show', compact('category', 'products', 'subcategories'));
    }
}
