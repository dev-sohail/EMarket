<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request)
    {
        $customer = auth()->user()?->customer ?? null;
        $sessionId = $request->session()->getId();

        $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

        // Don't create cart on page load - only create when item is added
        // This prevents empty carts from bots/scrapers. Pass an in-memory
        // (unsaved) Cart so the view can render the empty state without a null
        // $cart crashing on ->items / ->subtotal access.
        if (! $cart) {
            $cart = new Cart;
        }

        return view('store.cart.index', compact('cart'));
    }
}
