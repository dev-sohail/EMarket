<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajax\AjaxReturnRequest;
use App\Models\Order;
use App\Services\Return\ReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService
    ) {}

    public function store(AjaxReturnRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer;

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to submit a return request.',
                ], 401);
            }

            $order = Order::findOrFail($request->input('order_id'));

            if ($order->customer_id !== $customer->getKey()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 403);
            }

            $data = $request->validated();
            $data['customer_id'] = $customer->getKey();

            $return = $this->returnService->request($data);

            return response()->json([
                'success' => true,
                'message' => 'Return request submitted successfully.',
                'data' => [
                    'return' => $return->load(['items.orderItem', 'order']),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AJAX return request failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'We could not process your return request. Please contact support for assistance.',
            ], 500);
        }
    }
}
