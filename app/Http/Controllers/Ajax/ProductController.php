<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->input('q', '');

            // `?q[]=x` arrives as an array, and strlen() would TypeError.
            if (! is_string($query) || mb_strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $products = Product::with(['images', 'variants'])
                ->published()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->getKey(),
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'price' => $product->final_price,
                        'compare_price' => null,
                        'image' => $product->primary_image,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            // Never echo the exception message back to the client: a
            // QueryException carries table names, column names and driver text.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'We could not load products right now. Please try again later.',
            ], 500);
        }
    }

    public function filter(Request $request): JsonResponse
    {
        try {
            $query = Product::with(['images', 'variants', 'categories'])
                ->published();

            if ($request->filled('category_id')) {
                $query->whereHas('categories', fn ($q) => $q->where('categories._id', $request->input('category_id')));
            }

            if ($request->filled('brand_id')) {
                $query->whereHas('brands', fn ($q) => $q->where('brands._id', $request->input('brand_id')));
            }

            if ($request->filled('min_price')) {
                $query->where('base_price', '>=', $request->input('min_price'));
            }

            if ($request->filled('max_price')) {
                $query->where('base_price', '<=', $request->input('max_price'));
            }

            if ($request->filled('attributes') && is_array($request->input('attributes'))) {
                foreach ($request->input('attributes') as $attributeId => $valueIds) {
                    $valueIds = array_values(array_filter((array) $valueIds, 'is_scalar'));

                    $query->whereHas('attributeValues', function ($q) use ($attributeId, $valueIds) {
                        $q->where('attribute_id', $attributeId)
                            ->whereIn('attribute_values._id', $valueIds);
                    });
                }
            }

            $allowedSortColumns = ['name', 'sku', 'base_price', 'created_at'];
            $sortBy = in_array($request->input('sort_by', 'created_at'), $allowedSortColumns, true)
                ? $request->input('sort_by') : 'created_at';
            $requestedDir = $request->input('sort_dir', 'desc');
            $sortDir = is_string($requestedDir) && in_array(strtolower($requestedDir), ['asc', 'desc'], true)
                ? strtolower($requestedDir) : 'desc';
            $query->orderBy($sortBy, $sortDir);

            // Public endpoint: an unbounded `per_page` let a caller ask for the
            // whole catalogue (with images and variants) in one response.
            $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

            $products = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            // Never echo the exception message back to the client: a
            // QueryException carries table names, column names and driver text.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'We could not load products right now. Please try again later.',
            ], 500);
        }
    }

    public function variants(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_id' => ['required', 'exists:products,_id'],
                'attributes' => ['required', 'array'],
            ]);

            $product = Product::with(['variants', 'variants.images', 'variants.product', 'attributeValues.attribute'])
                ->findOrFail($request->input('product_id'));

            $selectedAttributes = $request->input('attributes', []);

            $matchingVariant = null;

            if ($product->variants->isNotEmpty()) {
                $variantQuery = $product->variants()->with('images', 'product');

                foreach ($selectedAttributes as $attributeId => $valueId) {
                    $variantQuery->whereHas('attributeValues', function ($q) use ($attributeId, $valueId) {
                        $q->where('attribute_values._id', $valueId)
                            ->where('variant_attribute_values.attribute_id', $attributeId);
                    });
                }

                $matchingVariant = $variantQuery->first();
            }

            // `final_price`, not the raw `price`: a variant on sale, or one whose
            // price is an add-on to the product base price, is charged and shown
            // at `final_price`, and quoting `price` here is how the variant list
            // advertised one figure while the cart charged another.
            $price = $matchingVariant?->final_price ?? $product->final_price;
            $listPrice = $matchingVariant?->list_price ?? (float) $product->base_price;

            return response()->json([
                'success' => true,
                'data' => [
                    'variant' => $matchingVariant,
                    'price' => $price,
                    'compare_price' => $listPrice > $price ? $listPrice : null,
                    'sku' => $matchingVariant?->sku ?? $product->sku,
                    'in_stock' => $matchingVariant ? ($matchingVariant->stock > 0) : false,
                    'image' => $matchingVariant?->image ?? $matchingVariant?->images->first()?->path ?? $product->primary_image,
                    'stock_quantity' => $matchingVariant?->stock_quantity ?? 0,
                ],
            ]);
        } catch (ValidationException $e) {
            // A bad request is a 422, not a server fault.
            throw $e;
        } catch (\Exception $e) {
            // Never echo the exception message back to the client: a
            // QueryException carries table names, column names and driver text.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'We could not load products right now. Please try again later.',
            ], 500);
        }
    }
}
