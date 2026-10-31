<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class WishlistController extends Controller
{
    public function index()
    {
        $customer = auth()->user()?->customer;

        if (! $customer) {
            return redirect()->route('login')->with('error', 'Please login to view your wishlist.');
        }

        $wishlist = Wishlist::firstOrCreate(['customer_id' => $customer->getKey()]);
        $items = $wishlist->items()->with('product.images', 'product.variants')->latest()->get();

        return view('customer.wishlist.index', compact('items', 'wishlist'));
    }

    public function toggle(Product $product)
    {
        try {
            $customer = auth()->user()?->customer;

            if (! $customer) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please login to manage your wishlist.',
                    ], 401);
                }

                return redirect()->route('login')->with('error', 'Please login to manage your wishlist.');
            }

            $wishlist = Wishlist::firstOrCreate(['customer_id' => $customer->getKey()]);

            $existingItem = WishlistItem::where('wishlist_id', $wishlist->getKey())
                ->where('product_id', $product->getKey())
                ->first();

            if ($existingItem) {
                $existingItem->delete();
                $added = false;
            } else {
                $wishlist->items()->create(['product_id' => $product->getKey()]);
                $added = true;
            }

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $added ? 'Added to wishlist.' : 'Removed from wishlist.',
                    'data' => ['added' => $added],
                ]);
            }

            return back()->with('success', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
        } catch (QueryException $e) {
            Log::error('Wishlist toggle failed: '.$e->getMessage(), ['exception' => $e]);

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update wishlist.',
                ], 500);
            }

            return back()->with('error', 'Failed to update wishlist.');
        }
    }
}
