<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajax\CheckoutCalculateRequest;
use App\Models\ShippingMethod;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingService;
use App\Services\Tax\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
        protected CouponService $couponService,
        protected ShippingService $shippingService,
        protected TaxService $taxService
    ) {}

    public function calculate(CheckoutCalculateRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();
            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            if (! $cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart is empty.',
                ], 422);
            }

            $pricing = $this->pricingService->calculateCartTotal($cart);

            $shippingCost = 0;
            if ($request->filled('shipping_method_id') && $request->filled('city')) {
                $shippingMethod = ShippingMethod::where('_id', $request->input('shipping_method_id'))->firstOrFail();
                $cartWeight = $cart->items->sum(fn ($item) => ($item->variant?->weight ?? $item->product->weight ?? 0) * $item->quantity);

                $result = $this->shippingService->calculate(
                    $request->input('city'),
                    $cartWeight,
                    $pricing['subtotal'],
                    (int) $cart->items->sum('quantity')
                );
                if ($result['available']) {
                    $matched = collect($result['methods'])->firstWhere('method_id', $shippingMethod->getKey());
                    if ($matched) {
                        $shippingCost = $matched['cost'];
                    }
                }
            }

            $discount = 0;
            $freeShipping = false;

            if ($request->filled('coupon_code')) {
                try {
                    $couponResult = $this->couponService->apply($request->input('coupon_code'), $cart);
                    $discount = $couponResult['discount'];
                    $freeShipping = $couponResult['free_shipping'] ?? false;
                    $cart->update(['coupon_code' => $request->input('coupon_code')]);
                } catch (\InvalidArgumentException $e) {
                    // Invalid coupon, continue without discount
                    $cart->update(['coupon_code' => null]);
                }
            } elseif ($cart->coupon_code) {
                $cart->update(['coupon_code' => null]);
            }

            if ($freeShipping) {
                $shippingCost = 0;
            }

            $taxableAmount = max(0, $pricing['subtotal'] - $discount);
            $tax = $taxableAmount > 0 ? $this->taxService->calculate($taxableAmount)['total_tax'] : 0;

            $totals = $this->pricingService->calculateGrandTotal(
                $pricing['subtotal'],
                $discount,
                $shippingCost,
                $tax
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'subtotal' => $totals['subtotal'],
                    'discount' => $totals['discount'],
                    'shipping' => $totals['shipping'],
                    'tax' => $totals['tax'],
                    'grand_total' => $totals['grand_total'],
                    'free_shipping' => $freeShipping,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Checkout calculation failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate checkout totals. Please try again.',
            ], 500);
        }
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $code = $request->input('coupon_code');

        // CouponService::apply() takes a string. A missing or array value would
        // raise a TypeError, which the \Exception catch below does not cover.
        if (! is_string($code) || trim($code) === '' || mb_strlen($code) > 50) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid coupon code.'], 422);
        }

        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();
            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            if (! $cart || $cart->items->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart is empty.']);
            }

            $couponResult = $this->couponService->apply(trim($code), $cart);

            $pricing = $this->pricingService->calculateCartTotal($cart);
            $grand = $this->pricingService->calculateGrandTotal(
                $pricing['subtotal'],
                $couponResult['discount'],
                0,
                0
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'discount' => $couponResult['discount'],
                    'free_shipping' => $couponResult['free_shipping'] ?? false,
                    'total' => $grand['grand_total'],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Apply coupon failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Failed to apply coupon. Please try again.']);
        }
    }
}
