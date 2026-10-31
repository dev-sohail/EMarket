<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajax\WishlistToggleRequest;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WishlistController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('throttle:30,1', only: ['toggle']),
        ];
    }

    public function toggle(WishlistToggleRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer;

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to manage your wishlist.',
                ], 401);
            }

            $product = Product::where('is_active', true)->findOrFail($request->validated('product_id'));

            $wishlist = Wishlist::firstOrCreate(['customer_id' => $customer->getKey()]);

            $existingItem = WishlistItem::where('wishlist_id', $wishlist->getKey())
                ->where('product_id', $product->getKey())
                ->first();

            if ($existingItem) {
                $existingItem->delete();
                $added = false;
                $message = 'Removed from wishlist.';
            } else {
                $wishlist->items()->create(['product_id' => $product->getKey()]);
                $added = true;
                $message = 'Added to wishlist.';
            }

            $count = $wishlist->items()->count();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'added' => $added,
                    'count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update wishlist.',
            ], 500);
        }
    }
}
