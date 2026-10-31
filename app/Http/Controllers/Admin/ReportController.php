<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn as ReturnModel;
use App\Services\Shared\CsvSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function index()
    {
        $totalSales = (float) Order::where('status', '!=', 'cancelled')->sum('grand_total');
        $totalOrders = Order::where('status', '!=', 'cancelled')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalCustomers = Customer::count();
        $totalProducts = Product::count();
        // Blank in config/app.php means "not configured", not 0: bound as ''
        // MySQL read the old comparison as `quantity <= 0`, so this was always
        // 0 once the setting was left empty.
        $lowStockThreshold = low_stock_threshold();

        $lowStockItems = $lowStockThreshold === null
            ? 0
            : Inventory::where('quantity', '<=', $lowStockThreshold)->where('quantity', '>', 0)->count();
        $totalReturns = ReturnModel::count();

        $summary = compact(
            'totalSales',
            'totalOrders',
            'pendingOrders',
            'totalCustomers',
            'totalProducts',
            'lowStockItems',
            'totalReturns'
        );

        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        $monthlySales = (float) Order::where('status', '!=', 'cancelled')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('grand_total');

        $topProducts = Product::withSum('orderItems as total_sold', 'quantity')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        $categorySales = Category::leftJoin('product_categories', 'categories._id', '=', 'product_categories.category_id')
            ->leftJoin('products', 'product_categories.product_id', '=', 'products._id')
            ->leftJoin('order_items', 'products._id', '=', 'order_items.product_id')
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders._id')
            ->where('orders.status', '!=', 'cancelled')
            ->select('categories.name', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity'), DB::raw('COALESCE(SUM(order_items.unit_price * order_items.quantity), 0) as total_revenue'))
            ->groupBy('categories._id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($cat) {
                $cat->total_revenue = (float) $cat->total_revenue;

                return $cat;
            });

        $recentOrders = Order::with(['customer', 'user', 'items'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(10)
            ->get();

        $monthlySalesData = Order::where('status', '!=', 'cancelled')
            ->whereYear('created_at', now()->year)
            ->get(['grand_total', 'created_at'])
            ->groupBy(fn ($o) => (int) $o->created_at->format('m'))
            ->map(fn ($group) => $group->sum('grand_total'))
            ->toArray();

        $monthlyLabels = [];
        $monthlyValues = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyLabels[] = Carbon::create()->month($i)->format('M');
            $monthlyValues[] = (float) ($monthlySalesData[$i] ?? 0);
        }

        return view('admin.report.index', compact(
            'summary',
            'totalSales',
            'totalOrders',
            'avgOrderValue',
            'monthlySales',
            'topProducts',
            'categorySales',
            'recentOrders',
            'monthlyLabels',
            'monthlyValues'
        ));
    }

    public function sales(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Order::where('payment_status', 'paid');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->get();

        $salesReport = [
            'total_sales' => $orders->sum('grand_total'),
            'total_orders' => $orders->count(),
            'average_order_value' => $orders->avg('grand_total'),
            'total_discount' => $orders->sum('discount'),
            'total_tax' => $orders->sum('tax'),
            'total_shipping' => $orders->sum('shipping_cost'),
            'daily_sales' => $orders->groupBy(fn ($o) => $o->created_at->format('Y-m-d'))
                ->map(fn ($group) => [
                    'date' => $group->first()->created_at->format('Y-m-d'),
                    'sales' => $group->sum('grand_total'),
                    'orders' => $group->count(),
                ])->values(),
        ];

        $filters = $request->only(['date_from', 'date_to']);

        return view('admin.reports.sales', compact('salesReport', 'filters'));
    }

    public function orders(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $query = Order::query();

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->get();

        $ordersReport = [
            'total_orders' => $orders->count(),
            'by_status' => $orders->groupBy('status')->map(fn ($group) => [
                'status' => $group->first()->status,
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
            ])->values(),
        ];

        $filters = $request->only(['date_from', 'date_to', 'status']);

        return view('admin.reports.orders', compact('ordersReport', 'filters'));
    }

    public function products(Request $request)
    {
        $request->validate([
            'sort_by' => 'nullable|in:sales,revenue,quantity',
            'sort_order' => 'nullable|in:asc,desc',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $productsReport = [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
        ];

        $filters = $request->only(['sort_by', 'sort_order', 'limit']);

        return view('admin.reports.products', compact('productsReport', 'filters'));
    }

    public function inventory()
    {
        $inventory = Inventory::with(['product', 'variant'])->get();

        // Inventory rows carry their own `reorder_level`; `low_stock_threshold`
        // is not an attribute of the model at all, so the comparison was
        // `quantity <= null`, which is never true in PHP — this table has always
        // rendered its empty state. Per-row reorder level wins, with the global
        // setting as the fallback for rows that have none.
        $threshold = low_stock_threshold();

        $inventoryReport = [
            'total_items' => $inventory->count(),
            'total_stock' => $inventory->sum('quantity'),
            'total_reserved' => $inventory->sum('reserved_quantity'),
            'low_stock_items' => $threshold === null
                ? collect()
                : $inventory->filter(fn ($i) => $i->quantity > 0
                    && $i->quantity <= ($i->reorder_level ?? $threshold))->values(),
            'out_of_stock' => $inventory->filter(fn ($i) => $i->quantity <= 0)->values(),
            'threshold' => $threshold,
        ];

        return view('admin.reports.inventory', compact('inventoryReport'));
    }

    public function customers(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Customer::withCount('orders');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $customers = $query->get();

        $customersReport = [
            'total_customers' => $customers->count(),
            'repeat_customers' => $customers->filter(fn ($c) => $c->orders_count > 1)->count(),
            'top_customers' => $customers->sortByDesc('orders_count')->take(10)->values(),
        ];

        $filters = $request->only(['date_from', 'date_to']);

        return view('admin.reports.customers', compact('customersReport', 'filters'));
    }

    public function returns(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:pending,approved,rejected,completed',
        ]);

        $query = ReturnModel::with('items');

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $returns = $query->get();

        $returnsReport = [
            'total_returns' => $returns->count(),
            'by_status' => $returns->groupBy('status')->map(fn ($group) => [
                'status' => $group->first()->status,
                'count' => $group->count(),
            ])->values(),
            'total_returned_items' => $returns->sum(fn ($r) => $r->items->sum('quantity')),
        ];

        $filters = $request->only(['date_from', 'date_to', 'status']);

        return view('admin.reports.returns', compact('returnsReport', 'filters'));
    }

    public function coupons(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Coupon::query();

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $coupons = $query->get();

        $couponsReport = [
            'total_coupons' => $coupons->count(),
            'active_coupons' => $coupons->where('is_active', true)->count(),
            'total_usage' => $coupons->sum('used_count'),
        ];

        $filters = $request->only(['date_from', 'date_to']);

        return view('admin.reports.coupons', compact('couponsReport', 'filters'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:sales,orders,products,inventory,customers,returns,coupons',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'format' => 'nullable|in:csv,xlsx',
        ]);

        $reportType = $request->input('report_type');
        $format = $request->input('format', 'csv');

        $data = match ($reportType) {
            'sales' => $this->getSalesExportData($request),
            'orders' => $this->getOrdersExportData($request),
            'products' => $this->getProductsExportData($request),
            'inventory' => $this->getInventoryExportData(),
            'customers' => $this->getCustomersExportData($request),
            'returns' => $this->getReturnsExportData($request),
            'coupons' => $this->getCouponsExportData($request),
            default => [],
        };

        $filename = "{$reportType}_report_".now()->format('Y-m-d_His');

        if ($format === 'csv') {
            return $this->exportCsv($data, $filename);
        }

        return $this->exportXlsx($data, $filename);
    }

    protected function getSalesExportData(Request $request): array
    {
        $query = Order::where('payment_status', 'paid');
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return $query->get()->map(fn ($order) => [
            'order_number' => $order->order_number,
            'date' => $order->created_at->format('Y-m-d'),
            'total' => $order->grand_total,
            'status' => $order->status,
        ])->toArray();
    }

    protected function getOrdersExportData(Request $request): array
    {
        $query = Order::query();
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->get()->map(fn ($order) => [
            'order_number' => $order->order_number,
            'date' => $order->created_at->format('Y-m-d'),
            'status' => $order->status,
            'total' => $order->grand_total,
        ])->toArray();
    }

    protected function getProductsExportData(Request $request): array
    {
        return Product::all()->map(fn ($product) => [
            'id' => $product->getKey(),
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'is_active' => $product->is_active ? 'Yes' : 'No',
        ])->toArray();
    }

    protected function getInventoryExportData(): array
    {
        return Inventory::with(['product', 'variant'])->get()->map(fn ($inv) => [
            'product' => $inv->product?->name,
            'variant' => $inv->variant?->name,
            'quantity' => $inv->quantity,
            'reserved' => $inv->reserved_quantity,
        ])->toArray();
    }

    protected function getCustomersExportData(Request $request): array
    {
        $query = Customer::query();
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return $query->get()->map(fn ($c) => [
            'name' => $c->first_name.' '.$c->last_name,
            'email' => $c->email,
            'phone' => $c->phone,
        ])->toArray();
    }

    protected function getReturnsExportData(Request $request): array
    {
        $query = ReturnModel::query();
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->get()->map(fn ($r) => [
            'return_number' => $r->return_number,
            'order_id' => $r->order_id,
            'status' => $r->status,
            'date' => $r->created_at->format('Y-m-d'),
        ])->toArray();
    }

    protected function getCouponsExportData(Request $request): array
    {
        $query = Coupon::query();
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return $query->get()->map(fn ($c) => [
            'code' => $c->code,
            'type' => $c->type,
            'value' => $c->value,
            'used_count' => $c->used_count,
        ])->toArray();
    }

    protected function exportCsv(array $data, string $filename)
    {
        if (empty($data)) {
            return back()->with('error', 'No data to export.');
        }

        $headers = array_keys($data[0]);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers);

            foreach ($data as $row) {
                fputcsv($handle, CsvSanitizer::row($row));
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    protected function exportXlsx(array $data, string $filename)
    {
        if (empty($data)) {
            return back()->with('error', 'No data to export.');
        }

        $headers = array_keys($data[0]);

        $callback = function () use ($data, $headers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $headers, "\t");

            foreach ($data as $row) {
                fputcsv($handle, CsvSanitizer::row($row), "\t");
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
        ]);
    }
}
