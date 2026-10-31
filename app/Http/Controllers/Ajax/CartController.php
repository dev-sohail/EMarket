<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajax\CartRequest;
use App\Models\Cart;
use App\Services\Cart\CartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('throttle:60,1', only: ['add', 'update', 'remove']),
        ];
    }

    public function __construct(
        protected CartService $cartService
    ) {}

    public function add(CartRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();

            return DB::transaction(function () use ($customer, $sessionId, $request) {
                $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

                if (! $cart) {
                    $cart = Cart::create([
                        'customer_id' => $customer?->getKey(),
                        'session_id' => $customer ? null : $sessionId,
                    ]);
                }

                $cartItem = $this->cartService->addItem(
                    $cart->getKey(),
                    $request->input('product_id'),
                    $request->input('variant_id'),
                    $request->input('quantity', 1)
                );

                $cartSummary = $this->cartService->getCartTotal($cart->getKey());

                return response()->json([
                    'success' => true,
                    'message' => 'Item added to cart.',
                    'data' => [
                        'cart_item' => $cartItem->load('product', 'variant'),
                        'summary' => $cartSummary,
                    ],
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            // InventoryService throws this for a stock or quantity problem the
            // shopper can act on, so the message is safe to pass through. The
            // catch is deliberately narrow: a QueryException is not an
            // InvalidArgumentException, so it cannot reach this branch, and the
            // generic \\Exception branch below is the one that must not echo.
            $status = str_contains($e->getMessage(), 'Insufficient stock') ? 422 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        } catch (\Exception $e) {
            Log::error('Add to cart failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to cart. Please try again.',
            ], 500);
        }
    }

    public function update(CartRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();

            $cartItem = $this->cartService->updateItem(
                $request->input('cart_item_id'),
                $request->input('quantity'),
                $customer?->getKey(),
                $sessionId
            );

            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);
            $cartSummary = $cart ? $this->cartService->getCartTotal($cart->getKey()) : null;

            return response()->json([
                'success' => true,
                'message' => 'Cart updated.',
                'data' => [
                    'cart_item' => $cartItem,
                    'summary' => $cartSummary,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cart update failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart. Please try again.',
            ], 500);
        }
    }

    public function remove(CartRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();

            $this->cartService->removeItem(
                $request->input('cart_item_id'),
                $customer?->getKey(),
                $sessionId
            );

            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);
            $cartSummary = $cart ? $this->cartService->getCartTotal($cart->getKey()) : null;

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'data' => ['summary' => $cartSummary],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Cart remove failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart. Please try again.',
            ], 500);
        }
    }

    public function count(Request $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();
            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $cart ? $cart->total_items : 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Cart count failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart count. Please try again.',
            ], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();
            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            // Explicit ownership verification
            if ($cart) {
                $this->verifyCartOwnership($cart, $customer?->getKey(), $sessionId);
            }

            if (! $cart) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subtotal' => 0,
                        'total_items' => 0,
                        'items_count' => 0,
                    ],
                ]);
            }

            $summary = $this->cartService->getCartTotal($cart->getKey());

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Cart summary failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load cart summary. Please try again.',
            ], 500);
        }
    }

    protected function verifyCartOwnership($cart, ?string $customerId, ?string $sessionId): void
    {
        if ($customerId !== null) {
            abort_if($cart->customer_id !== $customerId, 403);

            return;
        }

        if ($sessionId !== null) {
            abort_if($cart->session_id !== $sessionId, 403);
        }
    }
}
