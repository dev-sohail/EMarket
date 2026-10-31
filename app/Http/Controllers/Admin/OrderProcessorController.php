<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderProcessorController extends Controller
{
    /**
     * The stages this screen moves orders through. Terminal states an order
     * can reach outside the pipeline - `completed` and `returned` - are
     * deliberately absent: they are reached from the order detail screen.
     */
    private const PIPELINE_STATUSES = [
        'pending', 'confirmed', 'processing', 'ready_to_ship',
        'shipped', 'out_for_delivery', 'delivered', 'cancelled',
    ];

    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $request->validate([
            // Validated rather than trusted: `status` reaches a where clause
            // directly, so an unknown value used to return a silently empty
            // list, and the view's empty state then read an undefined array
            // key from the $statuses map.
            'status' => 'nullable|in:'.implode(',', self::PIPELINE_STATUSES),
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:20,50,100',
        ]);

        $status = $request->input('status', 'pending');
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;

        $orders = Order::with(['customer', 'user', 'items', 'shippingAddress'])
            ->where('status', $status)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q2) use ($search) {
                            $q2->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $statuses = [
            'pending' => ['label' => 'Pending', 'color' => 'yellow', 'icon' => 'fa-clock'],
            'confirmed' => ['label' => 'Confirmed', 'color' => 'blue', 'icon' => 'fa-check'],
            'processing' => ['label' => 'Processing', 'color' => 'indigo', 'icon' => 'fa-gear'],
            'ready_to_ship' => ['label' => 'Ready to Ship', 'color' => 'purple', 'icon' => 'box-open'],
            'shipped' => ['label' => 'Shipped', 'color' => 'orange', 'icon' => 'fa-truck'],
            'out_for_delivery' => ['label' => 'Out for Delivery', 'color' => 'cyan', 'icon' => 'fa-truck-fast'],
            'delivered' => ['label' => 'Delivered', 'color' => 'green', 'icon' => 'fa-check-double'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'red', 'icon' => 'fa-ban'],
        ];

        $perPageOptions = [20, 50, 100];

        return view('admin.order-processor.index', compact('orders', 'status', 'statusCounts', 'statuses', 'search', 'perPage', 'perPageOptions'));
    }

    public function verifyOrder(Request $request, Order $order)
    {
        $verification = $this->orderService->verifyOrder($order->getKey());

        return response()->json($verification);
    }

    public function markReadyForFulfillment(Request $request, Order $order)
    {
        $this->orderService->markReadyForFulfillment($order->getKey());

        return back()->with('success', 'Order marked as ready for fulfillment.');
    }

    public function updateShippingDetails(Request $request, Order $order)
    {
        $request->validate([
            'tracking_number' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255',
        ]);

        $order->update($request->only(['tracking_number', 'carrier']));

        return back()->with('success', 'Shipping details updated.');
    }

    public function generatePickingList(Order $order)
    {
        $order->load('items.product', 'items.variant', 'shippingAddress');

        return view('admin.order-processor.picking-list', compact('order'));
    }

    public function generateLabel(Order $order)
    {
        $label = $this->orderService->generateShippingLabel($order->getKey());

        return view('admin.order-processor.shipping-label', compact('label'));
    }

    public function generateReceipt(Order $order)
    {
        $receipt = $this->orderService->generateReceipt($order->getKey());

        return view('admin.order-processor.receipt', compact('receipt'));
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,_id',
            'status' => 'required|in:confirmed,processing,ready_to_ship,shipped,out_for_delivery,delivered,cancelled',
        ]);

        $count = 0;

        try {
            DB::transaction(function () use ($request, &$count) {
                foreach ($request->input('order_ids') as $orderId) {
                    $order = Order::find($orderId);
                    if ($order) {
                        $this->orderService->updateStatus($order->getKey(), $request->input('status'));
                        $count++;
                    }
                }
            });
        } catch (\Exception $e) {
            report($e);

            return back()->withInput()->with('error', 'Failed to update order status. Please try again.');
        }

        return back()->with('success', "{$count} order(s) updated to ".ucfirst($request->input('status')).'.');
    }
}
