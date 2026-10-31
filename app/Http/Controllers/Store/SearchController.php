<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q', '');
        $categorySlug = $request->input('category');

        $products = Product::with(['images', 'variants', 'categories', 'brands'])
            ->published()
            ->when($query, function ($q) use ($query) {
                $q->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('short_description', 'like', "%{$query}%");
                });
            })
            ->when($categorySlug, function ($q) use ($categorySlug) {
                $q->whereHas('categories', function ($sub) use ($categorySlug) {
                    $sub->where('slug', $categorySlug);
                });
            });

        if ($request->has('categories')) {
            $catSlugs = $request->input('categories', []);
            $catSlugs = is_array($catSlugs) ? $catSlugs : [$catSlugs];
            $products->whereHas('categories', function ($q) use ($catSlugs) {
                $q->whereIn('slug', $catSlugs);
            });
        }

        if ($request->has('brands')) {
            $brandSlugs = $request->input('brands', []);
            $brandSlugs = is_array($brandSlugs) ? $brandSlugs : [$brandSlugs];
            $products->whereHas('brands', function ($q) use ($brandSlugs) {
                $q->whereIn('slug', $brandSlugs);
            });
        }

        $products = $products->latest()->paginate(15)->appends($request->except('page'));

        return view('store.search.index', compact('products', 'query'));
    }
}
