<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreReturnRequest;
use App\Models\Order;
use App\Models\ProductReturn as ReturnModel;
use App\Services\Return\ReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    public function __construct(
        protected ReturnService $returnService
    ) {
        abort_if(! (bool) setting('returns_enabled', true), 404);
    }

    public function index()
    {
        $customer = auth()->user()->customer;
        $returns = $this->returnService->getCustomerReturns($customer->getKey());

        return view('customer.return.index', compact('returns'));
    }

    public function create(Order $order)
    {
        $customer = auth()->user()->customer;
        abort_if($order->customer_id !== $customer->getKey(), 403);

        abort_if(
            ! in_array($order->status->value, [OrderStatus::Delivered->value, OrderStatus::Shipped->value]),
            400, 'Order must be delivered or shipped to request a return.'
        );

        $existingReturn = $order->returns()
            ->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Completed->value])
            ->exists();

        abort_if($existingReturn, 400, 'A return request already exists for this order.');

        $order->load(['items.product', 'items.variant']);

        return view('customer.return.create', compact('order'));
    }

    public function store(StoreReturnRequest $request)
    {
        try {
            $customer = auth()->user()->customer;
            $order = Order::where('_id', $request->input('order_id'))
                ->where('customer_id', $customer->getKey())
                ->firstOrFail();

            $data = $request->validated();
            $data['customer_id'] = $customer->getKey();

            $return = $this->returnService->request($data);

            return redirect()->route('customer.returns.show', $return)
                ->with('success', 'Return request submitted successfully.');
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Return request failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'We could not process your return request. Please contact support for assistance.');
        }
    }

    public function show(ReturnModel $return)
    {
        $customer = auth()->user()->customer;
        abort_if($return->customer_id !== $customer->getKey(), 403);

        $return->load(['items.orderItem.product', 'order', 'statusHistories']);

        return view('customer.return.show', compact('return'));
    }
}
