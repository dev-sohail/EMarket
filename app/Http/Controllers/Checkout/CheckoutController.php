<?php

namespace App\Http\Controllers\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use App\Services\Coupon\CouponService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingService;
use App\Services\Tax\TaxService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller implements HasMiddleware
{
    /**
     * Rate limit the write-heavy checkout endpoints.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('throttle:10,1', only: ['process', 'generatePdf']),
        ];
    }

    public function __construct(
        protected CheckoutService $checkoutService,
        protected CartService $cartService,
        protected PricingService $pricingService,
        protected CouponService $couponService,
        protected ShippingService $shippingService,
        protected TaxService $taxService
    ) {}

    public function index(Request $request)
    {
        $customer = auth()->user()?->customer ?? null;
        $sessionId = $request->session()->getId();

        $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('shop')
                ->with('error', 'Your cart is empty.');
        }

        $pricing = $this->pricingService->calculateCartTotal($cart);
        $cartWeight = $cart->items->sum(fn ($item) => ($item->variant?->weight ?? $item->product->weight ?? 0) * $item->quantity);
        $addresses = $customer ? $customer->addresses()->orderBy('is_default', 'desc')->get() : collect();
        $shippingMethods = ShippingMethod::active()->orderBy('sort_order')->get();
        $paymentMethods = PaymentMethod::active()->ordered()->get();

        $idempotencyKey = $request->session()->get("checkout_idempotency_key_{$cart->getKey()}");

        if (! $idempotencyKey) {
            $idempotencyKey = Str::uuid()->toString();
            $request->session()->put("checkout_idempotency_key_{$cart->getKey()}", $idempotencyKey);
        }

        $discount = 0;
        $couponCode = $cart->coupon_code;

        if ($couponCode) {
            try {
                $couponResult = $this->couponService->apply($couponCode, $cart);
                $discount = $couponResult['discount'];
            } catch (\InvalidArgumentException $e) {
                $cart->update(['coupon_code' => null]);
            }
        }

        $totals = $this->pricingService->calculateGrandTotal(
            $pricing['subtotal'],
            $discount,
            0,
            0
        );

        $guestAddress = $request->session()->get('guest_address');

        return view('checkout.index', compact(
            'cart', 'pricing', 'addresses', 'shippingMethods', 'paymentMethods',
            'discount', 'couponCode', 'totals', 'guestAddress', 'idempotencyKey', 'cartWeight'
        ));
    }

    public function process(CheckoutRequest $request)
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();

            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            if (! $cart || $cart->items->isEmpty()) {
                return redirect()->route('shop')
                    ->with('error', 'Your cart is empty.');
            }

            $shippingMethod = ShippingMethod::where('is_active', true)->findOrFail($request->input('shipping_method_id'));
            $pricing = $this->pricingService->calculateCartTotal($cart);

            $discount = 0;
            $couponCode = null;
            $freeShipping = false;

            if ($request->filled('coupon_code')) {
                try {
                    $couponResult = $this->couponService->apply($request->input('coupon_code'), $cart);
                    $discount = $couponResult['discount'];
                    $couponCode = $request->input('coupon_code');
                    $freeShipping = $couponResult['free_shipping'] ?? false;
                    // Remember it on the cart: this block used to throw the code
                    // away, so re-opening the checkout showed a zero discount.
                    $cart->update(['coupon_code' => $couponCode]);
                } catch (\InvalidArgumentException $e) {
                    $cart->update(['coupon_code' => null]);
                }
            } elseif ($cart->coupon_code) {
                // The form was submitted without a code — the customer cleared it.
                $cart->update(['coupon_code' => null]);
            }

            $shippingAddress = $this->resolveShippingAddress($request, $customer);
            $shippingCost = $this->calculateShippingCost($cart, $shippingMethod, $pricing['subtotal'], $shippingAddress, $freeShipping);

            $taxableAmount = max(0, $pricing['subtotal'] - $discount);
            $tax = $taxableAmount > 0 ? $this->taxService->calculate($taxableAmount)['total_tax'] : 0;

            $data = [
                'cart_id' => $cart->getKey(),
                'idempotency_key' => $request->header('Idempotency-Key') ?: $request->input('idempotency_key') ?: $request->session()->get("checkout_idempotency_key_{$cart->getKey()}") ?: Str::uuid()->toString(),
                'customer_id' => $customer?->getKey(),
                'user_id' => auth()->id(),
                'payment_method' => $request->input('payment_method'),
                'shipping_address' => $shippingAddress,
                'shipping_method_id' => $shippingMethod->getKey(),
                'billing_address' => $shippingAddress,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'tax' => $tax,
                'coupon_code' => $couponCode,
                'notes' => $request->input('notes'),
                'charges' => [
                    [
                        'charge_type' => 'shipping',
                        'name' => $shippingMethod->name,
                        'amount' => $shippingCost,
                    ],
                ],
                'guest_name' => $request->input('name'),
                'guest_email' => $request->input('email'),
                'guest_phone' => $request->input('phone'),
            ];

            $order = $this->checkoutService->process($data);

            if (! $customer) {
                $response = redirect()->route('checkout.success', $order->order_number)
                    ->with('success', 'Order placed successfully!')
                    ->cookie('guest_order_token', $order->tracking_token, 60 * 24 * 30, null, null, true, true, false, 'Lax');

                if ($request->boolean('shipping_use_new_address')) {
                    $request->session()->put('guest_address', [
                        'full_name' => trim(($request->input('shipping_first_name') ?? '').' '.($request->input('shipping_last_name') ?? '')),
                        'address_line_1' => $request->input('shipping_address'),
                        'address_line_2' => $request->input('shipping_address2'),
                        'city' => $request->input('shipping_city'),
                        'state' => $request->input('shipping_state'),
                        'postal_code' => $request->input('shipping_zip_code'),
                        'phone' => $request->input('shipping_phone'),
                    ]);
                }

                return $response;
            }

            return redirect()->route('checkout.success', $order->order_number)
                ->with('success', 'Order placed successfully!');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()
                ->with('error', $e->getMessage());
        } catch (QueryException $e) {
            Log::error('Checkout processing failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'There was a technical issue with our system. Please try again in a few moments or contact support if the problem persists.');
        } catch (\Exception $e) {
            Log::error('Unexpected checkout error: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'We encountered an error while processing your order. Please check all information and try again, or contact our support team for assistance.');
        }
    }

    public function generatePdf(Request $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer ?? null;
            $sessionId = $request->session()->getId();

            $cart = $this->cartService->getCart($customer?->getKey(), $sessionId);

            if (! $cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your cart is empty.',
                ], 400);
            }

            $shippingMethod = ShippingMethod::where('is_active', true)->findOrFail(
                $request->input('shipping_method_id')
            );

            $pricing = $this->pricingService->calculateCartTotal($cart);
            $shippingAddress = $this->resolveShippingAddress($request, $customer);
            $shippingCost = $this->calculateShippingCost($cart, $shippingMethod, $pricing['subtotal'], $shippingAddress);

            $discount = 0;
            $couponCode = null;

            if ($request->filled('coupon_code')) {
                try {
                    $couponResult = $this->couponService->apply($request->input('coupon_code'), $cart);
                    $discount = $couponResult['discount'];
                    $cart->update(['coupon_code' => $request->input('coupon_code')]);
                } catch (\InvalidArgumentException $e) {
                    $cart->update(['coupon_code' => null]);
                }
            } elseif ($cart->coupon_code) {
                $cart->update(['coupon_code' => null]);
            }

            $taxableAmount = max(0, $pricing['subtotal'] - $discount);
            $tax = $taxableAmount > 0 ? $this->taxService->calculate($taxableAmount)['total_tax'] : 0;

            $totals = $this->pricingService->calculateGrandTotal(
                $pricing['subtotal'],
                $discount,
                $shippingCost,
                $tax
            );

            // Return calculated totals for PDF preview - do NOT create order
            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'order_number' => 'PREVIEW',
                        'status' => 'preview',
                    ],
                    'shipping_method' => $shippingMethod->name,
                    'shipping_cost' => $shippingCost,
                    'subtotal' => $pricing['subtotal'],
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $totals['grand_total'],
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (QueryException $e) {
            Log::error('Checkout PDF generation failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'System error occurred. Please try again.',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Checkout PDF generation error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF. Contact support.',
            ], 500);
        }
    }

    protected function calculateShippingCost(Cart $cart, ShippingMethod $shippingMethod, float $subtotal, array $shippingAddress, bool $freeShipping = false): float
    {
        if ($freeShipping) {
            return 0.0;
        }

        $cartWeight = $cart->items->sum(fn ($item) => ($item->variant?->weight ?? $item->product->weight ?? 0) * $item->quantity);
        $shippingCost = 0;

        $city = $shippingAddress['city'] ?? null;
        if ($city) {
            $result = $this->shippingService->calculate(
                $city,
                $cartWeight,
                $subtotal,
                (int) $cart->items->sum('quantity')
            );
            if ($result['available']) {
                $matched = collect($result['methods'])->firstWhere('method_id', $shippingMethod->getKey());
                if ($matched) {
                    $shippingCost = $matched['cost'];
                } else {
                    throw new \InvalidArgumentException('The selected shipping method is not available for your city. Please choose another shipping method.');
                }
            }
        }

        return $shippingCost;
    }

    public function success($orderNumber, Request $request)
    {
        $customer = auth()->user()?->customer ?? null;

        $order = Order::with(['items.product', 'items.variant', 'shippingAddress'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if ($customer) {
            abort_if($order->customer_id !== $customer->getKey(), 403);
        } else {
            $trackingToken = $request->cookie('guest_order_token');
            abort_if(! $order->tracking_token || ! $trackingToken || ! hash_equals($order->tracking_token, (string) $trackingToken), 403);
        }

        // A declined attempt leaves the order `failed` but still payable on
        // the same PaymentIntent, so it goes back to the payment page too.
        if ($order->payment_method === 'online'
            && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
            && $order->status !== OrderStatus::Cancelled
            && $order->stripe_payment_intent_id) {
            return redirect()->route('stripe.payment', $order->order_number);
        }

        return view('checkout.success', compact('order'));
    }

    protected function resolveShippingAddress(Request $request, ?Customer $customer): array
    {
        if ($request->boolean('shipping_use_new_address')) {
            return [
                'full_name' => trim(($request->input('shipping_first_name') ?? '').' '.($request->input('shipping_last_name') ?? '')),
                'address_line_1' => $request->input('shipping_address'),
                'address_line_2' => $request->input('shipping_address2'),
                'city' => $request->input('shipping_city'),
                'state' => $request->input('shipping_state'),
                'postal_code' => $request->input('shipping_zip_code'),
                'phone' => $request->input('shipping_phone'),
            ];
        }

        $address = CustomerAddress::findOrFail($request->input('shipping_address_id'));

        abort_if($customer && $address->customer_id !== $customer->getKey(), 403);
        abort_if(! $customer && $address->customer_id !== null, 403);

        return $address->toArray();
    }
}
